<?php

namespace App\Repository;

use App\Entity\CartItem;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<CartItem>
 */
class CartItemRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, CartItem::class);
    }

    //
    // example custom finder methods
    //
    // /**
    //  * @return CartItem[] Returns an array of CartItem objects
    //  */
    // public function findByExampleField($value): array
    // {
    //     return $this->createQueryBuilder('ci')
    //         ->andWhere('ci.exampleField = :val')
    //         ->setParameter('val', $value)
    //         ->orderBy('ci.id', 'ASC')
    //         ->setMaxResults(10)
    //         ->getQuery()
    //         ->getResult()
    //     ;
    // }
}
