<?php

namespace App\Service;

use App\Entity\ActivityLog;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Security\Core\User\UserInterface;

class ActivityLogService
{
    public function __construct(
        private EntityManagerInterface $entityManager
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
        $entry = $this->buildLogEntity($user, $action, $subject, $subjectId, $details);
        $this->entityManager->persist($entry);
        $this->entityManager->flush();
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
}
