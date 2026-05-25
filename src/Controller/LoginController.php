<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Authentication\AuthenticationUtils;

class LoginController extends AbstractController
{
    
    #[Route(path: '/login', name: 'app_login')]
    public function login(AuthenticationUtils $authenticationUtils): Response
    {
        if ($this->getUser()) {
            // Redirect based on role
            if ($this->isGranted('ROLE_ADMIN')) {
                return $this->redirectToRoute('app_dashboard');
            } elseif ($this->isGranted('ROLE_STAFF')) {
                return $this->redirectToRoute('app_staff_dashboard');
            } else {
                return $this->redirectToRoute('app_user_dashboard');
            }
        }

        // get the login error if there is one
        $error = $authenticationUtils->getLastAuthenticationError();
        // last username entered by the user
        $lastUsername = $authenticationUtils->getLastUsername();

        // Optional: enables Staff Google sign-in on the login page (ID-token flow).
        // Prefer Symfony env bags over getenv() for better cross-platform reliability.
        $googleClientId = $_ENV['GOOGLE_STAFF_OAUTH_CLIENT_ID']
            ?? $_SERVER['GOOGLE_STAFF_OAUTH_CLIENT_ID']
            ?? null;
        $googleClientId = is_string($googleClientId) ? trim($googleClientId) : null;
        if ($googleClientId === '') {
            $googleClientId = null;
        }

        return $this->render('security/login.html.twig', [
            'last_username' => $lastUsername,
            'error' => $error,
            'googleClientId' => $googleClientId,
        ]);
    }

    #[Route(path: '/logout', name: 'app_logout')]
    public function logout(): void
    {
        throw new \LogicException('This method can be blank - it will be intercepted by the logout key on your firewall.');
    }
}
