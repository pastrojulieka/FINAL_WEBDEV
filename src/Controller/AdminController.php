<?php

namespace App\Controller;

use App\Entity\User;
use App\Form\UserFormType;
use App\Repository\ActivityLogRepository;
use App\Repository\UserRepository;
use App\Repository\ProductRepository;
use App\Repository\OrderRepository;
use App\Repository\CustomerRepository;
use App\Repository\StockRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/admin')]
final class AdminController extends AbstractController
{
    #[Route('/users', name: 'app_admin_users')]
    public function index(UserRepository $userRepository): Response
    {
        // Avoid unbounded queries that can slow/hang on large datasets.
        $users = $userRepository->findBy([], ['id' => 'DESC'], 500);

        return $this->render('admin/users/index.html.twig', [
            'users' => $users,
        ]);
    }

    #[Route('/users/new', name: 'app_admin_users_new')]
    public function new(
        Request $request,
        EntityManagerInterface $entityManager,
        UserPasswordHasherInterface $passwordHasher
    ): Response {
        $user = new User();
        $form = $this->createForm(UserFormType::class, $user, ['is_edit' => false]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            // Get selected role and set it as array
            $role = $form->get('role')->getData();
            $user->setRoles([$role]);

            // Hash password
            $plainPassword = $form->get('plainPassword')->getData();
            if ($plainPassword) {
                $hashedPassword = $passwordHasher->hashPassword($user, $plainPassword);
                $user->setPassword($hashedPassword);
            }

            $entityManager->persist($user);
            $entityManager->flush();

            $this->addFlash('success', 'User created successfully!');

            return $this->redirectToRoute('app_admin_users');
        }
        
        // Set default role for form display
        $form->get('role')->setData('ROLE_USER');

        return $this->render('admin/users/new.html.twig', [
            'form' => $form,
        ]);
    }

    #[Route('/users/{id}/edit', name: 'app_admin_users_edit')]
    public function edit(
        Request $request,
        User $user,
        EntityManagerInterface $entityManager,
        UserPasswordHasherInterface $passwordHasher
    ): Response {
        // Store original roles
        $originalRoles = $user->getRoles();
        
        // Get the current main role (first role that's not ROLE_USER)
        $mainRole = 'ROLE_USER';
        foreach ($originalRoles as $role) {
            if ($role !== 'ROLE_USER') {
                $mainRole = $role;
                break;
            }
        }

        $form = $this->createForm(UserFormType::class, $user, ['is_edit' => true]);
        
        // Set the current role for the form display
        $form->get('role')->setData($mainRole);
        
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            // Get selected role and set it as array
            $role = $form->get('role')->getData();
            $user->setRoles([$role]);

            // Update password if provided
            $plainPassword = $form->get('plainPassword')->getData();
            if ($plainPassword) {
                $hashedPassword = $passwordHasher->hashPassword($user, $plainPassword);
                $user->setPassword($hashedPassword);
            }

            $entityManager->flush();

            $this->addFlash('success', 'User updated successfully!');

            return $this->redirectToRoute('app_admin_users');
        }

        return $this->render('admin/users/edit.html.twig', [
            'form' => $form,
            'user' => $user,
        ]);
    }

    #[Route('/users/{id}/delete', name: 'app_admin_users_delete', methods: ['POST'])]
    public function delete(
        Request $request,
        User $user,
        EntityManagerInterface $entityManager
    ): Response {
        // Prevent deleting yourself
        if ($user->getId() === $this->getUser()?->getId()) {
            $this->addFlash('error', 'You cannot delete your own account!');
            return $this->redirectToRoute('app_admin_users');
        }

        if ($this->isCsrfTokenValid('delete' . $user->getId(), $request->request->get('_token'))) {
            $entityManager->remove($user);
            $entityManager->flush();

            $this->addFlash('success', 'User deleted successfully!');
        }

        return $this->redirectToRoute('app_admin_users');
    }

    #[Route('/activity-logs', name: 'app_admin_activity_logs')]
    public function activityLogs(Request $request, ActivityLogRepository $activityLogRepository): Response
    {
        $search = $request->query->get('search', '');
        $logs = $activityLogRepository->searchLogs($search, 500);

        return $this->render('admin/activity_logs/index.html.twig', [
            'logs' => $logs,
            'search' => $search,
        ]);
    }

    #[Route('/records', name: 'app_admin_records')]
    public function viewAllRecords(
        ProductRepository $productRepository,
        OrderRepository $orderRepository,
        CustomerRepository $customerRepository,
        StockRepository $stockRepository,
        UserRepository $userRepository
    ): Response {
        // Avoid unbounded queries: these pages are dashboards, not exports.
        $limit = 200;
        $products = $productRepository->findBy([], ['id' => 'DESC'], $limit);
        $orders = $orderRepository->findBy([], ['id' => 'DESC'], $limit);
        $customers = $customerRepository->findBy([], ['id' => 'DESC'], $limit);
        $stocks = $stockRepository->findBy([], ['id' => 'DESC'], $limit);
        $users = $userRepository->findBy([], ['id' => 'DESC'], $limit);

        return $this->render('admin/records/index.html.twig', [
            'products' => $products,
            'orders' => $orders,
            'customers' => $customers,
            'stocks' => $stocks,
            'users' => $users,
            'limit' => $limit,
        ]);
    }
}

