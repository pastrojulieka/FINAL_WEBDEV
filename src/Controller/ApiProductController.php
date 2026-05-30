<?php

namespace App\Controller;

use App\Repository\ProductRepository;
use App\Service\LiveSnapshotService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api')]
class ApiProductController extends AbstractController
{
    public function __construct(
        private LiveSnapshotService $liveSnapshot,
    ) {
    }

    #[Route('/products', name: 'api_products', methods: ['GET'])]
    public function getProducts(Request $request, ProductRepository $productRepository): JsonResponse
    {
        $version = $this->liveSnapshot->fingerprintProducts();
        $clientVersion = $request->query->getString('version');

        if ($clientVersion !== '' && $clientVersion === $version) {
            return new JsonResponse([
                'success' => true,
                'changed' => false,
                'version' => $version,
            ], headers: ['Cache-Control' => 'no-store']);
        }

        $products = $productRepository->findAll();
        $productData = [];
        
        foreach ($products as $product) {
            $stockStatus = $this->calculateProductStockStatus($product);
            
            $productData[] = [
                'id' => $product->getId(),
                'name' => $product->getName(),
                'description' => $product->getDescription(),
                'price' => $product->getPrice(),
                'image' => $product->getImage(),
                'material' => $product->getMaterial(),
                'color' => $product->getColor(),
                'quantity' => $product->getQuantity(),
                'stockStatus' => $stockStatus,
                'categories' => $product->getCategories()->map(function($category) {
                    return [
                        'id' => $category->getId(),
                        'name' => $category->getName()
                    ];
                })->toArray()
            ];
        }
        
        return new JsonResponse([
            'success' => true,
            'changed' => true,
            'version' => $version,
            'data' => $productData,
        ], headers: ['Cache-Control' => 'no-store']);
    }

    #[Route('/products/{id}', name: 'api_product_show', methods: ['GET'])]
    public function getProduct(int $id, ProductRepository $productRepository): JsonResponse
    {
        $product = $productRepository->find($id);
        
        if (!$product) {
            return new JsonResponse([
                'success' => false,
                'message' => 'Product not found'
            ], 404);
        }
        
        $stockStatus = $this->calculateProductStockStatus($product);
        
        return new JsonResponse([
            'success' => true,
            'data' => [
                'id' => $product->getId(),
                'name' => $product->getName(),
                'description' => $product->getDescription(),
                'price' => $product->getPrice(),
                'image' => $product->getImage(),
                'material' => $product->getMaterial(),
                'color' => $product->getColor(),
                'quantity' => $product->getQuantity(),
                'stockStatus' => $stockStatus,
                'categories' => $product->getCategories()->map(function($category) {
                    return [
                        'id' => $category->getId(),
                        'name' => $category->getName()
                    ];
                })->toArray()
            ]
        ]);
    }

    private function calculateProductStockStatus($product): string
    {
        $stocks = $product->getStocks();
        $totalQuantity = 0;
        $hasLowStock = false;
        $hasOutOfStock = false;

        foreach ($stocks as $stock) {
            $totalQuantity += $stock->getQuantity();
            $status = $stock->getStatus();

            if ($status === 'Out of Stock') {
                $hasOutOfStock = true;
            } elseif ($status === 'Low Stock') {
                $hasLowStock = true;
            }
        }

        // Also consider the product's own quantity
        $totalQuantity += $product->getQuantity();

        // Determine overall status
        if ($hasOutOfStock || $totalQuantity === 0) {
            return 'Out of Stock';
        } elseif ($hasLowStock || $totalQuantity < 10) {
            return 'Low Stock';
        } else {
            return 'In Stock';
        }
    }
}
