<?php

namespace App\Controller;

use App\Entity\User;
use App\Service\EmailVerificationService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;

#[Route('/api')]
class ApiRegistrationController extends AbstractController
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private UserPasswordHasherInterface $passwordHasher,
        private EmailVerificationService $emailVerificationService,
        private ValidatorInterface $validator
    ) {}

    #[Route('/register', name: 'api_register', methods: ['POST'])]
    public function register(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);

        if (!is_array($data) || !isset($data['email'], $data['password'])) {
            return $this->json([
                'success' => false,
                'message' => 'Email and password are required',
            ], 400);
        }

        if (!filter_var($data['email'], \FILTER_VALIDATE_EMAIL)) {
            return $this->json([
                'success' => false,
                'message' => 'Invalid email address',
            ], 400);
        }

        if (strlen((string) $data['password']) < 6) {
            return $this->json([
                'success' => false,
                'message' => 'Password must be at least 6 characters long',
            ], 400);
        }

        $existingEmail = $this->entityManager
            ->getRepository(User::class)
            ->findOneBy(['email' => $data['email']]);

        if ($existingEmail) {
            return $this->json([
                'success' => false,
                'message' => 'Email already registered',
            ], 409);
        }

        $user = new User();
        $user->setEmail($data['email']);
        $user->setRoles(['ROLE_USER']);

        $hashedPassword = $this->passwordHasher->hashPassword($user, (string) $data['password']);
        $user->setPassword($hashedPassword);

        $verificationToken = $this->emailVerificationService->generateVerificationToken();
        $user->setVerificationToken($verificationToken);
        $user->setIsVerified(false);

        $errors = $this->validator->validate($user);
        if (count($errors) > 0) {
            $errorMessages = [];
            foreach ($errors as $error) {
                $errorMessages[] = $error->getMessage();
            }

            return $this->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $errorMessages,
            ], 400);
        }

        $this->entityManager->persist($user);
        $this->entityManager->flush();

        $verificationUrl = $this->generateUrl(
            'app_verify_email',
            ['token' => $verificationToken],
            UrlGeneratorInterface::ABSOLUTE_URL
        );

        try {
            $this->emailVerificationService->sendVerificationEmail($user, $verificationUrl);
        } catch (\Throwable) {
            // User can request resend later
        }

        return $this->json([
            'success' => true,
            'message' => 'Registration successful. Please check your email to verify your account.',
            'user' => [
                'id' => $user->getId(),
                'email' => $user->getEmail(),
                'isVerified' => $user->isVerified(),
                'roles' => $user->getRoles(),
            ],
        ], 201);
    }
}

