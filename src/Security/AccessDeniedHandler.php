<?php

namespace App\Security;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;
use Symfony\Component\Security\Http\Authorization\AccessDeniedHandlerInterface;
use Symfony\Bundle\SecurityBundle\Security;

class AccessDeniedHandler implements AccessDeniedHandlerInterface
{
    public function __construct(
        private UrlGeneratorInterface $urlGenerator,
        private RequestStack $requestStack,
        private Security $security
    ) {
    }

    public function handle(Request $request, AccessDeniedException $accessDeniedException): ?RedirectResponse
    {
        $session = $this->requestStack->getSession();
        
        // Get current user
        $user = $this->security->getUser();
        
        // If user is staff (but not admin), redirect to staff dashboard
        if ($user && $this->security->isGranted('ROLE_STAFF') && !$this->security->isGranted('ROLE_ADMIN')) {
            $session->getFlashBag()->add('error', 'Access Denied. Staff members cannot access admin-only pages.');
            return new RedirectResponse($this->urlGenerator->generate('app_staff_dashboard'));
        }

        // For other cases, redirect to login
        $session->getFlashBag()->add('error', 'Access Denied. You do not have permission to access this page.');
        return new RedirectResponse($this->urlGenerator->generate('app_login'));
    }
}

