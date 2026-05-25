<?php

namespace App\Repository;

use App\Entity\Stock;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Stock>
 */
class StockRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Stock::class);
    }

    private function em(): \Doctrine\ORM\EntityManagerInterface
    {
        return $this->getEntityManager();
    }

    public function save(Stock $entity, bool $flush = false): void
    {
        $this->em()->persist($entity);

        if ($flush) {
            $this->em()->flush();
        }
    }

    public function remove(Stock $entity, bool $flush = false): void
    {
        $this->em()->remove($entity);

        if ($flush) {
            $this->em()->flush();
        }
    }

    public function getStatusSummary(): array
    {
        $results = $this->createQueryBuilder('s')
            ->select('s.status AS status', 'COUNT(s.id) AS items', 'SUM(s.quantity) AS quantity')
            ->groupBy('s.status')
            ->getQuery()
            ->getArrayResult();

        $summary = [
            'In Stock' => ['items' => 0, 'quantity' => 0],
            'Low Stock' => ['items' => 0, 'quantity' => 0],
            'Out of Stock' => ['items' => 0, 'quantity' => 0],
        ];

        foreach ($results as $row) {
            $status = $row['status'] ?? 'In Stock';
            $summary[$status] = [
                'items' => (int) $row['items'],
                'quantity' => (int) $row['quantity'],
            ];
        }

        return $summary;
    }
}

