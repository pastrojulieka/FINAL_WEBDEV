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
 * Records LOGIN and LOGOUT for all users (viewing logs is admin-only).
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
        $user = $event->getUser();
        $subjectId = $this->resolveUserSubjectId($user);

        $this->activityLogService->log(
            $user,
            ActivityLog::ACTION_LOGIN,
            'User',
            $subjectId,
            'User logged in'
        );
    }

    public function onLogout(LogoutEvent $event): void
    {
        $token = $event->getToken();
        $user = $token?->getUser();
        if (!$user instanceof UserInterface) {
            return;
        }

        $subjectId = $this->resolveUserSubjectId($user);
        $this->activityLogService->log(
            $user,
            ActivityLog::ACTION_LOGOUT,
            'User',
            $subjectId,
            'User logged out'
        );
    }

    private function resolveUserSubjectId(UserInterface $user): ?string
    {
        if ($user instanceof User && $user->getId() !== null) {
            return (string) $user->getId();
        }

        return $user->getUserIdentifier();
    }
}
