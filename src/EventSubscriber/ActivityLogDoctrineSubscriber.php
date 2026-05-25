<?php

namespace App\EventSubscriber;

use App\Entity\ActivityLog;
use App\Service\ActivityLogService;
use Doctrine\Bundle\DoctrineBundle\EventSubscriber\EventSubscriberInterface;
use Doctrine\ORM\Event\PostFlushEventArgs;
use Doctrine\ORM\Event\PostPersistEventArgs;
use Doctrine\ORM\Event\PostUpdateEventArgs;
use Doctrine\ORM\Event\PreRemoveEventArgs;
use Doctrine\ORM\Events;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\Security\Core\User\UserInterface;

/**
 * Records CREATE / UPDATE / DELETE when the acting user is ROLE_ADMIN (deferred to postFlush).
 */
final class ActivityLogDoctrineSubscriber implements EventSubscriberInterface
{
    /** @var list<array{action: string, subject: string, subjectId: ?string}> */
    private array $pending = [];

    private bool $flushInProgress = false;

    public function __construct(
        private ActivityLogService $activityLogService,
        private Security $security,
    ) {
    }

    public function getSubscribedEvents(): array
    {
        return [
            Events::postPersist => 'postPersist',
            Events::postUpdate => 'postUpdate',
            Events::preRemove => 'preRemove',
            Events::postFlush => 'postFlush',
        ];
    }

    public function postPersist(PostPersistEventArgs $args): void
    {
        if ($this->flushInProgress) {
            return;
        }
        $entity = $args->getObject();
        if (!$this->shouldAudit($entity)) {
            return;
        }
        $user = $this->security->getUser();
        if (!$user instanceof UserInterface || !$this->security->isGranted('ROLE_ADMIN')) {
            return;
        }
        $subjectId = $this->getEntityId($entity);
        $this->pending[] = [
            'action' => ActivityLog::ACTION_CREATE,
            'subject' => $this->shortClassName($entity),
            'subjectId' => $subjectId,
        ];
    }

    public function postUpdate(PostUpdateEventArgs $args): void
    {
        if ($this->flushInProgress) {
            return;
        }
        $entity = $args->getObject();
        if (!$this->shouldAudit($entity)) {
            return;
        }
        if (!$this->security->isGranted('ROLE_ADMIN')) {
            return;
        }
        $subjectId = $this->getEntityId($entity);
        $this->pending[] = [
            'action' => ActivityLog::ACTION_UPDATE,
            'subject' => $this->shortClassName($entity),
            'subjectId' => $subjectId,
        ];
    }

    public function preRemove(PreRemoveEventArgs $args): void
    {
        if ($this->flushInProgress) {
            return;
        }
        $entity = $args->getObject();
        if (!$this->shouldAudit($entity)) {
            return;
        }
        if (!$this->security->isGranted('ROLE_ADMIN')) {
            return;
        }
        $subjectId = $this->getEntityId($entity);
        $this->pending[] = [
            'action' => ActivityLog::ACTION_DELETE,
            'subject' => $this->shortClassName($entity),
            'subjectId' => $subjectId,
        ];
    }

    public function postFlush(PostFlushEventArgs $args): void
    {
        if ($this->flushInProgress || $this->pending === []) {
            return;
        }
        $user = $this->security->getUser();
        if (!$user instanceof UserInterface || !$this->security->isGranted('ROLE_ADMIN')) {
            $this->pending = [];

            return;
        }

        $em = $args->getObjectManager();
        $pending = $this->pending;
        $this->pending = [];
        $this->flushInProgress = true;

        try {
            foreach ($pending as $row) {
                $log = $this->activityLogService->buildLogEntity(
                    $user,
                    $row['action'],
                    $row['subject'],
                    $row['subjectId'],
                    null
                );
                $em->persist($log);
            }
            $em->flush();
        } finally {
            $this->flushInProgress = false;
        }
    }

    private function shouldAudit(object $entity): bool
    {
        return !$entity instanceof ActivityLog;
    }

    private function shortClassName(object $entity): string
    {
        return (new \ReflectionClass($entity))->getShortName();
    }

    private function getEntityId(object $entity): ?string
    {
        if (!method_exists($entity, 'getId')) {
            return null;
        }
        $id = $entity->getId();

        return $id !== null ? (string) $id : null;
    }
}
