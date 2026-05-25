<?php

namespace App\Repository;

use App\Entity\ActivityLog;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<ActivityLog>
 */
class ActivityLogRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ActivityLog::class);
    }

    /**
     * @return ActivityLog[]
     */
    public function findRecent(int $limit = 500): array
    {
        return $this->createQueryBuilder('a')
            ->orderBy('a.createdAt', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    /**
     * @return ActivityLog[]
     */
    public function searchLogs(string $query, int $limit = 500): array
    {
        $qb = $this->createQueryBuilder('a');

        if (!empty($query)) {
            $qb->where($qb->expr()->orX(
                $qb->expr()->like('a.userEmail', ':query'),
                $qb->expr()->like('a.action', ':query'),
                $qb->expr()->like('a.subject', ':query'),
                $qb->expr()->like('a.subjectId', ':query'),
                $qb->expr()->like('a.details', ':query')
            ))
            ->setParameter('query', '%' . $query . '%');
        }

        return $qb->orderBy('a.createdAt', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }
}
