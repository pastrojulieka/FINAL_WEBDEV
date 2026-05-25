<?php

namespace App\Repository;

use App\Entity\Product;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Product>
 */
class ProductRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Product::class);
    }

    /**
     * @return Product[] Returns an array of Product objects matching the search query
     */
    public function search(string $query): array
    {
        return $this->createQueryBuilder('p')
            ->where('p.name LIKE :query')
            ->orWhere('p.description LIKE :query')
            ->orWhere('p.material LIKE :query')
            ->orWhere('p.color LIKE :query')
            ->setParameter('query', '%' . $query . '%')
            ->orderBy('p.name', 'ASC')
            ->setMaxResults(20)
            ->getQuery()
            ->getResult();
    }
}