<?php

namespace App\Controller;

use App\Entity\ActivityLog;
use App\Entity\Customer;
use App\Form\CustomerType;
use App\Repository\CustomerRepository;
use App\Service\ActivityLogService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/customer')]
final class CustomerController extends AbstractController
{
    public function __construct(
        private ActivityLogService $activityLogService,
    ) {
    }
    #[Route(name: 'app_customer_index', methods: ['GET'])]
    public function index(CustomerRepository $customerRepository): Response
    {
        return $this->render('customer/index.html.twig', [
            'customers' => $customerRepository->findAll(),
        ]);
    }

    #[Route('/new', name: 'app_customer_new', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $entityManager): Response
    {
        $customer = new Customer();
        $form = $this->createForm(CustomerType::class, $customer);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            // Set createdBy if user is logged in
            if ($this->getUser()) {
                $customer->setCreatedBy($this->getUser());
            }
            $entityManager->persist($customer);
            $entityManager->flush();

            // Log the creation
            $this->activityLogService->log(
                $this->getUser(),
                ActivityLog::ACTION_CREATE,
                'Customer',
                (string)$customer->getId(),
                "Created customer: {$customer->getName()}"
            );

            $this->addFlash('success', 'Customer created successfully!');
            return $this->redirectToRoute('app_customer_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('customer/new.html.twig', [
            'customer' => $customer,
            'form' => $form,
        ]);
    }

    #[Route('/{id}', name: 'app_customer_show', methods: ['GET'])]
    public function show(Customer $customer): Response
    {
        return $this->render('customer/show.html.twig', [
            'customer' => $customer,
        ]);
    }

    #[Route('/{id}/edit', name: 'app_customer_edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, Customer $customer, EntityManagerInterface $entityManager): Response
    {
        // Staff and Admin can edit all customers - no restrictions
        // If you want to add any permission checks, do it here

        $form = $this->createForm(CustomerType::class, $customer);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->flush();

            // Log the update
            $this->activityLogService->log(
                $this->getUser(),
                ActivityLog::ACTION_UPDATE,
                'Customer',
                (string)$customer->getId(),
                "Updated customer: {$customer->getName()}"
            );

            $this->addFlash('success', 'Customer updated successfully!');
            return $this->redirectToRoute('app_customer_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('customer/edit.html.twig', [
            'customer' => $customer,
            'form' => $form,
        ]);
    }

    #[Route('/{id}', name: 'app_customer_delete', methods: ['POST'])]
    public function delete(Request $request, Customer $customer, EntityManagerInterface $entityManager): Response
    {
        // Check if staff can only delete their own records
        // Admin can delete any customer
        if ($this->isGranted('ROLE_STAFF') && !$this->isGranted('ROLE_ADMIN')) {
            if ($customer->getCreatedBy() !== $this->getUser()) {
                $this->addFlash('error', 'You can only delete your own customers.');
                return $this->redirectToRoute('app_customer_index', [], Response::HTTP_SEE_OTHER);
            }
        }

        if ($this->isCsrfTokenValid('delete'.$customer->getId(), $request->getPayload()->getString('_token'))) {
            $customerName = $customer->getName(); // Store name before deletion
            $customerId = (string)$customer->getId();

            $entityManager->remove($customer);
            $entityManager->flush();

            // Log the deletion
            $this->activityLogService->log(
                $this->getUser(),
                ActivityLog::ACTION_DELETE,
                'Customer',
                $customerId,
                "Deleted customer: {$customerName}"
            );

            $this->addFlash('success', 'Customer deleted successfully!');
        }

        return $this->redirectToRoute('app_customer_index', [], Response::HTTP_SEE_OTHER);
    }
}