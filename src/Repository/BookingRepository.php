<?php

namespace App\Repository;

use App\Entity\Booking;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Booking>
 *
 * @method Booking|null find($id, $lockMode = null, $lockVersion = null)
 * @method Booking|null findOneBy(array $criteria, array $orderBy = null)
 * @method Booking[]    findAll()
 * @method Booking[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class BookingRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Booking::class);
    }

    public function save(Booking $entity, bool $flush = false): void
    {
        $this->getEntityManager()->persist($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function remove(Booking $entity, bool $flush = false): void
    {
        $this->getEntityManager()->remove($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    /**
     * @return Booking[] Returns an array of Booking objects matching the search query
     */
    public function search(string $query): array
    {
        return $this->createQueryBuilder('b')
            ->where('b.customer_name LIKE :query')
            ->orWhere('b.service_type LIKE :query')
            ->orWhere('b.description LIKE :query')
            ->orWhere('b.status LIKE :query')
            ->orWhere('CAST(b.id AS string) LIKE :query')
            ->setParameter('query', '%' . $query . '%')
            ->orderBy('b.created_at', 'DESC')
            ->setMaxResults(20)
            ->getQuery()
            ->getResult();
    }

    /**
     * Find bookings by customer name
     */
    public function findByCustomerName(string $customerName): array
    {
        return $this->createQueryBuilder('b')
            ->where('b.customer_name = :customerName')
            ->setParameter('customerName', $customerName)
            ->orderBy('b.booking_date', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Find bookings by date range
     */
    public function findByDateRange(\DateTime $startDate, \DateTime $endDate): array
    {
        return $this->createQueryBuilder('b')
            ->where('b.booking_date >= :startDate')
            ->andWhere('b.booking_date <= :endDate')
            ->setParameter('startDate', $startDate)
            ->setParameter('endDate', $endDate)
            ->orderBy('b.booking_date', 'ASC')
            ->getQuery()
            ->getResult();
    }
}
