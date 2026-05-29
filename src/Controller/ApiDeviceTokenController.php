<?php

namespace App\Controller;

use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api')]
class ApiDeviceTokenController extends AbstractController
{
    #[Route('/device-token', name: 'api_device_token', methods: ['POST'])]
    public function registerToken(Request $request, EntityManagerInterface $entityManager): JsonResponse
    {
        $user = $this->getUser();
        if (!$user instanceof User) {
            return new JsonResponse([
                'success' => false,
                'message' => 'Authentication required',
            ], 401);
        }

        $data = json_decode($request->getContent(), true);
        $token = isset($data['token']) ? trim((string) $data['token']) : '';

        if ($token === '') {
            return new JsonResponse([
                'success' => false,
                'message' => 'Device token is required',
            ], 400);
        }

        $user->setFcmToken($token);
        $entityManager->flush();

        return new JsonResponse([
            'success' => true,
            'message' => 'Device token registered',
        ]);
    }
}
