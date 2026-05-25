<?php

namespace App\Controller;

use App\Entity\Stock;
use App\Repository\StockRepository;
use App\Repository\ProductRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api')]
class ApiStockController extends AbstractController
{
    #[Route('/stocks', name: 'api_stocks', methods: ['GET'])]
    public function getStocks(StockRepository $stockRepository): JsonResponse
    {
        $stocks = $stockRepository->findAll();
        $stockData = [];
        
        foreach ($stocks as $stock) {
            $stockData[] = [
                'id' => $stock->getId(),
                'product' => [
                    'id' => $stock->getProduct()->getId(),
                    'name' => $stock->getProduct()->getName(),
                    'price' => $stock->getProduct()->getPrice(),
                    'image' => $stock->getProduct()->getImage()
                ],
                'quantity' => $stock->getQuantity(),
                'status' => $stock->getStatus(),
                'created_by' => $stock->getCreatedBy() ? $stock->getCreatedBy()->getEmail() : null
            ];
        }
        
        return new JsonResponse([
            'success' => true,
            'data' => $stockData
        ]);
    }

    #[Route('/stocks/product/{productId}', name: 'api_stocks_by_product', methods: ['GET'])]
    public function getStocksByProduct(int $productId, StockRepository $stockRepository, ProductRepository $productRepository): JsonResponse
    {
        $product = $productRepository->find($productId);
        
        if (!$product) {
            return new JsonResponse([
                'success' => false,
                'message' => 'Product not found'
            ], 404);
        }

        $stocks = $stockRepository->findBy(['product' => $product]);
        $stockData = [];
        
        foreach ($stocks as $stock) {
            $stockData[] = [
                'id' => $stock->getId(),
                'quantity' => $stock->getQuantity(),
                'status' => $stock->getStatus(),
                'created_by' => $stock->getCreatedBy() ? $stock->getCreatedBy()->getEmail() : null
            ];
        }
        
        return new JsonResponse([
            'success' => true,
            'data' => $stockData
        ]);
    }

    #[Route('/stocks/{id}', name: 'api_stock_show', methods: ['GET'])]
    public function getStock(int $id, StockRepository $stockRepository): JsonResponse
    {
        $stock = $stockRepository->find($id);
        
        if (!$stock) {
            return new JsonResponse([
                'success' => false,
                'message' => 'Stock not found'
            ], 404);
        }

        return new JsonResponse([
            'success' => true,
            'data' => [
                'id' => $stock->getId(),
                'product' => [
                    'id' => $stock->getProduct()->getId(),
                    'name' => $stock->getProduct()->getName(),
                    'price' => $stock->getProduct()->getPrice(),
                    'image' => $stock->getProduct()->getImage(),
                    'material' => $stock->getProduct()->getMaterial(),
                    'color' => $stock->getProduct()->getColor()
                ],
                'quantity' => $stock->getQuantity(),
                'status' => $stock->getStatus(),
                'created_by' => $stock->getCreatedBy() ? $stock->getCreatedBy()->getEmail() : null
            ]
        ]);
    }

    #[Route('/stocks/low-stock', name: 'api_stocks_low', methods: ['GET'])]
    public function getLowStockItems(StockRepository $stockRepository): JsonResponse
    {
        $stocks = $stockRepository->createQueryBuilder('s')
            ->where('s.status = :status OR s.quantity < :threshold')
            ->setParameter('status', 'Low Stock')
            ->setParameter('threshold', 10)
            ->getQuery()
            ->getResult();

        $stockData = [];
        
        foreach ($stocks as $stock) {
            $stockData[] = [
                'id' => $stock->getId(),
                'product' => [
                    'id' => $stock->getProduct()->getId(),
                    'name' => $stock->getProduct()->getName(),
                    'price' => $stock->getProduct()->getPrice(),
                    'image' => $stock->getProduct()->getImage()
                ],
                'quantity' => $stock->getQuantity(),
                'status' => $stock->getStatus()
            ];
        }
        
        return new JsonResponse([
            'success' => true,
            'data' => $stockData
        ]);
    }

    #[Route('/stocks/out-of-stock', name: 'api_stocks_out', methods: ['GET'])]
    public function getOutOfStockItems(StockRepository $stockRepository): JsonResponse
    {
        $stocks = $stockRepository->createQueryBuilder('s')
            ->where('s.status = :status OR s.quantity = :zero')
            ->setParameter('status', 'Out of Stock')
            ->setParameter('zero', 0)
            ->getQuery()
            ->getResult();

        $stockData = [];
        
        foreach ($stocks as $stock) {
            $stockData[] = [
                'id' => $stock->getId(),
                'product' => [
                    'id' => $stock->getProduct()->getId(),
                    'name' => $stock->getProduct()->getName(),
                    'price' => $stock->getProduct()->getPrice(),
                    'image' => $stock->getProduct()->getImage()
                ],
                'quantity' => $stock->getQuantity(),
                'status' => $stock->getStatus()
            ];
        }
        
        return new JsonResponse([
            'success' => true,
            'data' => $stockData
        ]);
    }
}
