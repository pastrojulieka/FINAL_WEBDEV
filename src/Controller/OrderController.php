<?php

namespace App\Controller;

use App\Entity\ActivityLog;
use App\Entity\Order;
use App\Form\Order1Type;
use App\Repository\OrderRepository;
use App\Service\ActivityLogService;
use App\Repository\UserRepository;
use App\Service\PushNotificationService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/order')]
final class OrderController extends AbstractController
{
    public function __construct(
        private ActivityLogService $activityLogService,
        private PushNotificationService $pushNotificationService,
        private UserRepository $userRepository,
    ) {
    }

    #[Route(name: 'app_order_index', methods: ['GET'])]
    public function index(OrderRepository $orderRepository): Response
    {
        $this->denyAccessUnlessGranted('ROLE_STAFF');

        return $this->render('order/index.html.twig', [
            'orders' => $orderRepository->findBy([], ['date' => 'DESC']),
        ]);
    }

    #[Route('/new', name: 'app_order_new', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $entityManager): Response
    {
        $this->denyAccessUnlessGranted('ROLE_STAFF');

        $order = new Order();
        $order->setStatus(Order::STATUS_PENDING);
        $order->setDate(new \DateTime());
        $order->setDeliveryDate(new \DateTimeImmutable('+7 days'));

        $form = $this->createForm(Order1Type::class, $order);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->finalizeOrder($order);

            if ($this->getUser()) {
                $order->setCreatedBy($this->getUser());
            }

            $entityManager->persist($order);
            $entityManager->flush();

            $this->activityLogService->log(
                $this->getUser(),
                ActivityLog::ACTION_CREATE,
                'Order',
                (string) $order->getId(),
                "Created order #{$order->getId()}"
            );

            $this->addFlash('success', 'Order created successfully!');
            return $this->redirectToRoute('app_order_index', [], Response::HTTP_SEE_OTHER);
        }

        if ($form->isSubmitted() && !$form->isValid()) {
            foreach ($form->getErrors(true) as $error) {
                $this->addFlash('error', $error->getMessage());
            }
        }

        return $this->render('order/new.html.twig', [
            'order' => $order,
            'form' => $form,
        ]);
    }

    #[Route('/{id}', name: 'app_order_show', requirements: ['id' => '\d+'], methods: ['GET'])]
    public function show(Order $order): Response
    {
        $this->denyAccessUnlessGranted('ROLE_STAFF');

        return $this->render('order/show.html.twig', [
            'order' => $order,
        ]);
    }

    #[Route('/{id}/edit', name: 'app_order_edit', requirements: ['id' => '\d+'], methods: ['GET', 'POST'])]
    public function edit(Request $request, Order $order, EntityManagerInterface $entityManager): Response
    {
        $this->denyAccessUnlessGranted('ROLE_STAFF');

        $previousStatus = $order->getStatus();
        $form = $this->createForm(Order1Type::class, $order);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->finalizeOrder($order);
            $entityManager->persist($order);
            $entityManager->flush();

            if ($previousStatus !== $order->getStatus()) {
                $this->notifyCustomerAboutOrderUpdate($order, $previousStatus);
            }

            $this->activityLogService->log(
                $this->getUser(),
                ActivityLog::ACTION_UPDATE,
                'Order',
                (string) $order->getId(),
                "Updated order #{$order->getId()}"
            );

            $this->addFlash('success', 'Order updated successfully!');
            return $this->redirectToRoute('app_order_index', [], Response::HTTP_SEE_OTHER);
        }

        if ($form->isSubmitted() && !$form->isValid()) {
            foreach ($form->getErrors(true) as $error) {
                $this->addFlash('error', $error->getMessage());
            }
        }

        return $this->render('order/edit.html.twig', [
            'order' => $order,
            'form' => $form,
        ]);
    }

    #[Route('/{id}/status', name: 'app_order_status', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function updateStatus(Request $request, Order $order, EntityManagerInterface $entityManager): Response
    {
        $this->denyAccessUnlessGranted('ROLE_STAFF');

        $token = $request->getPayload()->getString('_token');
        if ($token === '' && $request->request->has('_token')) {
            $token = (string) $request->request->get('_token');
        }

        if (!$this->isCsrfTokenValid('order_status'.$order->getId(), $token)) {
            if ($request->isXmlHttpRequest()) {
                return $this->json(['success' => false, 'message' => 'Invalid security token'], 400);
            }
            $this->addFlash('error', 'Invalid security token. Please try again.');

            return $this->redirectToRoute('app_order_index', [], Response::HTTP_SEE_OTHER);
        }

        $newStatus = $request->getPayload()->getString('status');
        if ($newStatus === '' && $request->request->has('status')) {
            $newStatus = (string) $request->request->get('status');
        }

        if (!Order::isValidStatus($newStatus)) {
            if ($request->isXmlHttpRequest()) {
                return $this->json(['success' => false, 'message' => 'Invalid order status'], 400);
            }
            $this->addFlash('error', 'Invalid order status.');

            return $this->redirectToRoute('app_order_index', [], Response::HTTP_SEE_OTHER);
        }

        $previousStatus = $order->getStatus();
        if ($previousStatus === $newStatus) {
            if ($request->isXmlHttpRequest()) {
                return $this->json([
                    'success' => true,
                    'message' => 'Status unchanged',
                    'status' => $newStatus,
                ]);
            }

            return $this->redirectToRoute('app_order_index', [], Response::HTTP_SEE_OTHER);
        }

        $order->setStatus($newStatus);
        $entityManager->flush();

        $this->notifyCustomerAboutOrderUpdate($order, $previousStatus);

        $statusMessage = sprintf('Set order #%d status to %s', $order->getId(), $newStatus);
        if ($newStatus === Order::STATUS_COMPLETE) {
            $statusMessage .= sprintf(' (₱%s added to revenue)', number_format($order->getTotalAmount() ?? 0, 2));
        }

        $this->activityLogService->log(
            $this->getUser(),
            ActivityLog::ACTION_UPDATE,
            'Order',
            (string) $order->getId(),
            $statusMessage
        );

        if ($request->isXmlHttpRequest()) {
            return $this->json([
                'success' => true,
                'message' => 'Order status updated',
                'status' => $newStatus,
                'order_id' => $order->getId(),
            ]);
        }

        $this->addFlash('success', sprintf('Order #%d marked as %s.', $order->getId(), ucfirst($newStatus)));

        return $this->redirectToRoute('app_order_index', [], Response::HTTP_SEE_OTHER);
    }

    #[Route('/{id}/delete', name: 'app_order_delete', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function delete(Request $request, Order $order, EntityManagerInterface $entityManager): Response
    {
        $this->denyAccessUnlessGranted('ROLE_STAFF');

        if ($this->isGranted('ROLE_STAFF') && !$this->isGranted('ROLE_ADMIN')) {
            if ($order->getCreatedBy() !== $this->getUser()) {
                $this->addFlash('error', 'You can only delete your own orders.');
                return $this->redirectToRoute('app_order_index', [], Response::HTTP_SEE_OTHER);
            }
        }

        $token = $request->getPayload()->getString('_token');
        if ($token === '' && $request->request->has('_token')) {
            $token = (string) $request->request->get('_token');
        }

        if ($this->isCsrfTokenValid('delete'.$order->getId(), $token)) {
            $orderId = (string) $order->getId();

            $entityManager->remove($order);
            $entityManager->flush();

            $this->activityLogService->log(
                $this->getUser(),
                ActivityLog::ACTION_DELETE,
                'Order',
                $orderId,
                "Deleted order #{$orderId}"
            );

            $this->addFlash('success', 'Order deleted successfully!');
        } else {
            $this->addFlash('error', 'Invalid security token. Please try again.');
        }

        return $this->redirectToRoute('app_order_index', [], Response::HTTP_SEE_OTHER);
    }

    private function finalizeOrder(Order $order): void
    {
        if ($order->getQuantity() !== null && $order->getPrice() !== null) {
            $order->setTotalAmount($order->getPrice() * $order->getQuantity());
        }

        if ($order->getDate() === null) {
            $order->setDate(new \DateTime());
        }

        if ($order->getDeliveryDate() === null) {
            $order->setDeliveryDate(new \DateTimeImmutable('+7 days'));
        }
    }

    private function notifyCustomerAboutOrderUpdate(Order $order, ?string $previousStatus = null): void
    {
        $customer = $order->getCreatedBy();
        if ($customer === null) {
            return;
        }

        $freshCustomer = $this->userRepository->find($customer->getId());
        if ($freshCustomer === null) {
            return;
        }

        $order->setCreatedBy($freshCustomer);
        $this->pushNotificationService->notifyOrderStatusChanged($order, $previousStatus);
    }
}
