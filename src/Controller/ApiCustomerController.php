<?php

namespace App\Controller;

use App\Entity\Customer;
use App\Entity\User;
use App\Repository\CustomerRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Validator\Validator\ValidatorInterface;

#[Route('/api')]
class ApiCustomerController extends AbstractController
{
    #[Route('/profile', name: 'api_profile', methods: ['GET'])]
    public function getProfile(CustomerRepository $customerRepository): JsonResponse
    {
        $user = $this->getUser();
        
        if (!$user) {
            return new JsonResponse([
                'success' => false,
                'message' => 'Authentication required'
            ], 401);
        }

        /** @var User $user */
        // Try to find customer by email address
        $customer = $customerRepository->findOneBy(['email_address' => $user->getUserIdentifier()]);
        
        $profileData = [
            'id' => $user->getId(),
            'email' => $user->getUserIdentifier(),
            'roles' => $user->getRoles(),
            'isVerified' => $user->isVerified(),
            'customer' => null
        ];

        if ($customer) {
            $profileData['customer'] = [
                'id' => $customer->getId(),
                'name' => $customer->getName(),
                'email_address' => $customer->getEmailAddress(),
                'phone_number' => $customer->getPhoneNumber(),
                'address' => $customer->getAddress()
            ];
        }

        return new JsonResponse([
            'success' => true,
            'data' => $profileData
        ]);
    }

    #[Route('/profile', name: 'api_update_profile', methods: ['PUT', 'POST'])]
    public function updateProfile(
        Request $request, 
        CustomerRepository $customerRepository,
        EntityManagerInterface $entityManager,
        ValidatorInterface $validator
    ): JsonResponse {
        $user = $this->getUser();
        
        if (!$user) {
            return new JsonResponse([
                'success' => false,
                'message' => 'Authentication required'
            ], 401);
        }

        $data = json_decode($request->getContent(), true);

        if (!$data) {
            return new JsonResponse([
                'success' => false,
                'message' => 'Invalid JSON data'
            ], 400);
        }

        /** @var User $user */
        // Find or create customer record
        $customer = $customerRepository->findOneBy(['email_address' => $user->getUserIdentifier()]);
        
        if (!$customer) {
            $customer = new Customer();
            $customer->setEmailAddress($user->getUserIdentifier());
            $customer->setCreatedBy($user);
        }

        // Update customer fields if provided
        if (isset($data['name'])) {
            $customer->setName($data['name']);
        }
        
        if (isset($data['phone_number'])) {
            $customer->setPhoneNumber($data['phone_number']);
        }
        
        if (isset($data['address'])) {
            $customer->setAddress($data['address']);
        }

        // Validate the customer entity
        $errors = $validator->validate($customer);
        if (count($errors) > 0) {
            $errorMessages = [];
            foreach ($errors as $error) {
                $errorMessages[] = $error->getMessage();
            }

            return new JsonResponse([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $errorMessages,
            ], 400);
        }

        $entityManager->persist($customer);
        $entityManager->flush();

        return new JsonResponse([
            'success' => true,
            'message' => 'Profile updated successfully',
            'data' => [
                'id' => $customer->getId(),
                'name' => $customer->getName(),
                'email_address' => $customer->getEmailAddress(),
                'phone_number' => $customer->getPhoneNumber(),
                'address' => $customer->getAddress()
            ]
        ]);
    }

    #[Route('/customers', name: 'api_customers', methods: ['GET'])]
    public function getCustomers(CustomerRepository $customerRepository): JsonResponse
    {
        $user = $this->getUser();
        
        if (!$user) {
            return new JsonResponse([
                'success' => false,
                'message' => 'Authentication required'
            ], 401);
        }

        // Only staff and admin can view all customers
        if (!in_array('ROLE_STAFF', $user->getRoles()) && !in_array('ROLE_ADMIN', $user->getRoles())) {
            return new JsonResponse([
                'success' => false,
                'message' => 'Access denied'
            ], 403);
        }

        $customers = $customerRepository->findAll();
        $customerData = [];
        
        foreach ($customers as $customer) {
            $customerData[] = [
                'id' => $customer->getId(),
                'name' => $customer->getName(),
                'email_address' => $customer->getEmailAddress(),
                'phone_number' => $customer->getPhoneNumber(),
                'address' => $customer->getAddress(),
                'created_by' => $customer->getCreatedBy() ? $customer->getCreatedBy()->getEmail() : null
            ];
        }
        
        return new JsonResponse([
            'success' => true,
            'data' => $customerData
        ]);
    }
}
