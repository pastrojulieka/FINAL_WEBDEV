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
}