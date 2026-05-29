<?php

namespace App\Controller;

use App\Entity\ActivityLog;
use App\Entity\Order;
use App\Form\Order1Type;
use App\Repository\OrderRepository;
use App\Service\ActivityLogService;
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

        $form = $this->createForm(Order1Type::class, $order);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->finalizeOrder($order);
            $entityManager->flush();

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

        return $this->render('order/edit.html.twig', [
            'order' => $order,
            'form' => $form,
        ]);
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
}
