<?php

namespace App\EventSubscriber;

use App\Entity\ActivityLog;
use App\Entity\User;
use App\Service\ActivityLogService;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Security\Http\Event\LoginSuccessEvent;
use Symfony\Component\Security\Http\Event\LogoutEvent;

/**
 * Records LOGIN and LOGOUT for web sessions (viewing logs is admin-only).
 */
final class ActivityLogSecuritySubscriber implements EventSubscriberInterface
{
    private const SESSION_LOGIN_LOGGED = '_activity_login_logged';
    private const SESSION_LOGOUT_LOGGED = '_activity_logout_logged';

    public function __construct(
        private ActivityLogService $activityLogService,
        private RequestStack $requestStack,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            LoginSuccessEvent::class => 'onLoginSuccess',
            LogoutEvent::class => 'onLogout',
        ];
    }

    public function onLoginSuccess(LoginSuccessEvent $event): void
    {
        if ($event->getFirewallName() !== 'main') {
            return;
        }

        $session = $this->requestStack->getSession();
        if ($session->get(self::SESSION_LOGIN_LOGGED)) {
            return;
        }

        $this->activityLogService->logAuthEvent($event->getUser(), ActivityLog::ACTION_LOGIN);
        $session->set(self::SESSION_LOGIN_LOGGED, true);
        $session->remove(self::SESSION_LOGOUT_LOGGED);
    }

    public function onLogout(LogoutEvent $event): void
    {
        if ($event->getFirewallName() !== 'main') {
            return;
        }

        $token = $event->getToken();
        $user = $token?->getUser();
        if (!$user instanceof UserInterface) {
            return;
        }

        $session = $this->requestStack->getSession();
        $session->remove(self::SESSION_LOGIN_LOGGED);

        if ($session->get(self::SESSION_LOGOUT_LOGGED)) {
            return;
        }

        $this->activityLogService->logAuthEvent($user, ActivityLog::ACTION_LOGOUT);
        $session->set(self::SESSION_LOGOUT_LOGGED, true);
    }
}
