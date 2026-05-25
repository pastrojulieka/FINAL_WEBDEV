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
        return $this->render('order/index.html.twig', [
            'orders' => $orderRepository->findAll(),
        ]);
    }

    #[Route('/new', name: 'app_order_new', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $entityManager): Response
    {
        $order = new Order();
        $form = $this->createForm(Order1Type::class, $order);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            // Set createdBy if user is logged in
            if ($this->getUser()) {
                $order->setCreatedBy($this->getUser());
            }
            $entityManager->persist($order);
            $entityManager->flush();

            // Log the creation
            $this->activityLogService->log(
                $this->getUser(),
                ActivityLog::ACTION_CREATE,
                'Order',
                (string)$order->getId(),
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

    #[Route('/{id}', name: 'app_order_show', methods: ['GET'])]
    public function show(Order $order): Response
    {
        return $this->render('order/show.html.twig', [
            'order' => $order,
        ]);
    }

    #[Route('/{id}/edit', name: 'app_order_edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, Order $order, EntityManagerInterface $entityManager): Response
    {
        // Staff and Admin can edit all orders - no restrictions
        // If you want to add any permission checks, do it here

        $form = $this->createForm(Order1Type::class, $order);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->flush();

            // Log the update
            $this->activityLogService->log(
                $this->getUser(),
                ActivityLog::ACTION_UPDATE,
                'Order',
                (string)$order->getId(),
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

    #[Route('/{id}', name: 'app_order_delete', methods: ['POST'])]
    public function delete(Request $request, Order $order, EntityManagerInterface $entityManager): Response
    {
        // Check if staff can only delete their own records
        // Admin can delete any order
        if ($this->isGranted('ROLE_STAFF') && !$this->isGranted('ROLE_ADMIN')) {
            if ($order->getCreatedBy() !== $this->getUser()) {
                $this->addFlash('error', 'You can only delete your own orders.');
                return $this->redirectToRoute('app_order_index', [], Response::HTTP_SEE_OTHER);
            }
        }

        if ($this->isCsrfTokenValid('delete'.$order->getId(), $request->getPayload()->getString('_token'))) {
            $orderId = (string)$order->getId();

            $entityManager->remove($order);
            $entityManager->flush();

            // Log the deletion
            $this->activityLogService->log(
                $this->getUser(),
                ActivityLog::ACTION_DELETE,
                'Order',
                $orderId,
                "Deleted order #{$orderId}"
            );

            $this->addFlash('success', 'Order deleted successfully!');
        }

        return $this->redirectToRoute('app_order_index', [], Response::HTTP_SEE_OTHER);
    }
}