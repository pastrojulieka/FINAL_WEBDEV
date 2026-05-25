<?php

namespace App\Repository;

use App\Entity\Customer;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Customer>
 */
class CustomerRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Customer::class);
    }

    /**
     * @return Customer[] Returns an array of Customer objects matching the search query
     */
    public function search(string $query): array
    {
        return $this->createQueryBuilder('c')
            ->where('c.name LIKE :query')
            ->orWhere('c.email_address LIKE :query')
            ->orWhere('c.phone_number LIKE :query')
            ->orWhere('c.address LIKE :query')
            ->setParameter('query', '%' . $query . '%')
            ->orderBy('c.name', 'ASC')
            ->setMaxResults(20)
            ->getQuery()
            ->getResult();
    }
}