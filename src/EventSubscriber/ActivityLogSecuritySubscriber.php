<?php

namespace App\EventSubscriber;

use App\Entity\ActivityLog;
use App\Entity\User;
use App\Service\ActivityLogService;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Security\Http\Event\LoginSuccessEvent;
use Symfony\Component\Security\Http\Event\LogoutEvent;

/**
 * Records LOGIN and LOGOUT for web sessions (viewing logs is admin-only).
 */
final class ActivityLogSecuritySubscriber implements EventSubscriberInterface
{
    public function __construct(
        private ActivityLogService $activityLogService,
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

        $this->activityLogService->logAuthEvent($event->getUser(), ActivityLog::ACTION_LOGIN);
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

        $this->activityLogService->logAuthEvent($user, ActivityLog::ACTION_LOGOUT);
    }
}
