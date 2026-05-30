<?php

namespace App\Repository;

use App\Entity\Order;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Order>
 */
class OrderRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Order::class);
    }

    /**
     * @return Order[] Returns an array of Order objects matching the search query
     */
    public function search(string $query): array
    {
        return $this->createQueryBuilder('o')
            ->where('o.customer_name LIKE :query')
            ->orWhere('o.product_name LIKE :query')
            ->orWhere('o.material LIKE :query')
            ->orWhere('o.color LIKE :query')
            ->setParameter('query', '%' . $query . '%')
            ->orderBy('o.date', 'DESC')
            ->setMaxResults(20)
            ->getQuery()
            ->getResult();
    }

    /**
     * Revenue counts only completed orders (status = complete).
     */
    public function sumCompletedRevenueSince(\DateTimeInterface $since): float
    {
        return (float) ($this->createQueryBuilder('o')
            ->select('SUM(o.total_amount)')
            ->where('o.date >= :since')
            ->andWhere('o.status = :status')
            ->setParameter('since', $since)
            ->setParameter('status', Order::STATUS_COMPLETE)
            ->getQuery()
            ->getSingleScalarResult() ?? 0);
    }

    public function sumCompletedRevenueBetween(\DateTimeInterface $from, \DateTimeInterface $to): float
    {
        return (float) ($this->createQueryBuilder('o')
            ->select('SUM(o.total_amount)')
            ->where('o.date >= :from')
            ->andWhere('o.date < :to')
            ->andWhere('o.status = :status')
            ->setParameter('from', $from)
            ->setParameter('to', $to)
            ->setParameter('status', Order::STATUS_COMPLETE)
            ->getQuery()
            ->getSingleScalarResult() ?? 0);
    }

    public function countOrdersSince(\DateTimeInterface $since): int
    {
        return (int) ($this->createQueryBuilder('o')
            ->select('COUNT(o.id)')
            ->where('o.date >= :since')
            ->setParameter('since', $since)
            ->getQuery()
            ->getSingleScalarResult() ?? 0);
    }

    public function countOrdersBetween(\DateTimeInterface $from, \DateTimeInterface $to): int
    {
        return (int) ($this->createQueryBuilder('o')
            ->select('COUNT(o.id)')
            ->where('o.date >= :from')
            ->andWhere('o.date < :to')
            ->setParameter('from', $from)
            ->setParameter('to', $to)
            ->getQuery()
            ->getSingleScalarResult() ?? 0);
    }
}