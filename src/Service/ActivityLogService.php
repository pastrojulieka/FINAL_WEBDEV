<?php

namespace App\Service;

use App\Entity\ActivityLog;
use App\Entity\User;
use App\Repository\ActivityLogRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Security\Core\User\UserInterface;

class ActivityLogService
{
    private const AUTH_DEDUP_SECONDS = 120;

    public function __construct(
        private EntityManagerInterface $entityManager,
        private ActivityLogRepository $activityLogRepository,
    ) {
    }

    /**
     * Persist a log entry immediately (login, logout, or deferred flush completion).
     */
    public function log(
        ?UserInterface $user,
        string $action,
        ?string $subject = null,
        ?string $subjectId = null,
        ?string $details = null,
    ): void {
        if ($this->shouldSkipDuplicateAuthEvent($user, $action)) {
            return;
        }

        $entry = $this->buildLogEntity($user, $action, $subject, $subjectId, $details);
        $this->entityManager->persist($entry);
        $this->entityManager->flush();
    }

    /**
     * Record login/logout once per user within a short window (avoids duplicate rows).
     */
    public function logAuthEvent(
        ?UserInterface $user,
        string $action,
        ?string $details = null,
    ): void {
        $this->log(
            $user,
            $action,
            'User',
            $this->resolveUserSubjectId($user),
            $details ?? ($action === ActivityLog::ACTION_LOGIN ? 'User logged in' : 'User logged out')
        );
    }

    /**
     * Build a row without flushing (used by Doctrine postFlush to avoid nested flush).
     */
    public function buildLogEntity(
        ?UserInterface $user,
        string $action,
        ?string $subject = null,
        ?string $subjectId = null,
        ?string $details = null,
    ): ActivityLog {
        $entry = new ActivityLog();
        $entry->setAction($action);

        if ($user instanceof User) {
            $entry->setUser($user);
            $entry->setUserEmail($user->getEmail() ?? $user->getUserIdentifier());
            $entry->setRole($this->resolvePrimaryRole($user));
        } elseif ($user !== null) {
            $entry->setUserEmail($user->getUserIdentifier());
            $entry->setRole($this->resolvePrimaryRole($user));
        } else {
            $entry->setUserEmail('—');
            $entry->setRole('—');
        }

        $entry->setSubject($subject);
        $entry->setSubjectId($subjectId);
        $entry->setDetails($details);

        return $entry;
    }

    public function resolvePrimaryRole(UserInterface $user): string
    {
        $roles = $user->getRoles();
        foreach (['ROLE_ADMIN', 'ROLE_STAFF', 'ROLE_USER'] as $r) {
            if (\in_array($r, $roles, true)) {
                return $r;
            }
        }

        return $roles[0] ?? 'ROLE_USER';
    }

    private function shouldSkipDuplicateAuthEvent(?UserInterface $user, string $action): bool
    {
        if (!\in_array($action, [ActivityLog::ACTION_LOGIN, ActivityLog::ACTION_LOGOUT], true)) {
            return false;
        }

        $email = $this->resolveUserEmail($user);
        if ($email === '') {
            return false;
        }

        return $this->activityLogRepository->hasRecentAuthEvent($email, $action, self::AUTH_DEDUP_SECONDS);
    }

    private function resolveUserEmail(?UserInterface $user): string
    {
        if ($user instanceof User) {
            return $user->getEmail() ?? $user->getUserIdentifier();
        }

        return $user?->getUserIdentifier() ?? '';
    }

    private function resolveUserSubjectId(?UserInterface $user): ?string
    {
        if ($user instanceof User && $user->getId() !== null) {
            return (string) $user->getId();
        }

        return $user?->getUserIdentifier();
    }
}
