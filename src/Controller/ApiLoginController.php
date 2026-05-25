<?php

namespace App\Controller;

use App\Repository\UserRepository;
use Lexik\Bundle\JWTAuthenticationBundle\Services\JWTTokenManagerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;

class ApiLoginController extends AbstractController
{
    #[Route('/api/login', name: 'api_login', methods: ['POST','GET'])]
    public function login(
        Request $request,
        UserRepository $userRepository,
        UserPasswordHasherInterface $passwordHasher,
        JWTTokenManagerInterface $jwtManager
    ): JsonResponse {

        $data = json_decode($request->getContent(), true);

        if (!$data || !isset($data['email'], $data['password'])) {
            return new JsonResponse([
                'success' => false,
                'message' => 'Invalid JSON',
            ], 400);
        }

        $user = $userRepository->findOneBy(['email' => $data['email']]);

        if (!$user) {
            return new JsonResponse([
                'success' => false,
                'message' => 'User not found',
            ], 401);
        }

        if (!$passwordHasher->isPasswordValid($user, $data['password'])) {
            return new JsonResponse([
                'success' => false,
                'message' => 'Invalid credentials',
            ], 401);
        }

        if (!$user->isVerified()) {
            return new JsonResponse([
                'success' => false,
                'message' => 'Please verify your email address before logging in',
                'verified' => false,
            ], 403);
        }

        $token = $jwtManager->create($user);

        return new JsonResponse([
            'success' => true,
            'message' => 'Login successful',
            'token' => $token,
            'user' => [
                'id' => $user->getId(),
                'email' => $user->getEmail(),
                'roles' => $user->getRoles(),
                'isVerified' => $user->isVerified(),
            ]
        ]);
    }
}