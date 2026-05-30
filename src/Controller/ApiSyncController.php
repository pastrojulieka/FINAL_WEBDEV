<?php

namespace App\Controller;

use App\Entity\User;
use App\Repository\OrderRepository;
use App\Repository\ProductRepository;
use App\Repository\StockRepository;
use App\Service\LiveSnapshotService;
use App\Service\OrderLiveService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api')]
class ApiSyncController extends AbstractController
{
    public function __construct(
        private LiveSnapshotService $liveSnapshot,
        private OrderLiveService $orderLiveService,
        private OrderRepository $orderRepository,
        private ProductRepository $productRepository,
        private StockRepository $stockRepository,
    ) {
    }

    /**
     * Single endpoint for mobile real-time sync (poll every 4–8s with ?version=).
     */
    #[Route('/sync', name: 'api_sync', methods: ['GET'])]
    public function sync(Request $request): JsonResponse
    {
        $user = $this->getUser();
        if (!$user instanceof User) {
            return new JsonResponse(['success' => false, 'message' => 'Authentication required'], 401);
        }

        $roles = $user->getRoles();
        $isStaffOrAdmin = \in_array('ROLE_STAFF', $roles, true) || \in_array('ROLE_ADMIN', $roles, true);

        $versions = [
            'global' => $this->liveSnapshot->fingerprintGlobal($user),
            'orders' => $isStaffOrAdmin
                ? $this->liveSnapshot->fingerprintOrders()
                : $this->orderLiveService->computeFingerprint(
                    $this->orderRepository->findBy(['createdBy' => $user], ['date' => 'DESC'])
                ),
            'products' => $this->liveSnapshot->fingerprintProducts(),
            'stocks' => $this->liveSnapshot->fingerprintStocks(),
            'activity_logs' => $this->liveSnapshot->fingerprintActivityLogs(),
        ];

        if ($isStaffOrAdmin) {
            $versions['customers'] = $this->liveSnapshot->fingerprintCustomers();
            $versions['users'] = $this->liveSnapshot->fingerprintUsers();
            $versions['dashboard'] = $this->liveSnapshot->fingerprintDashboard();
        }

        $clientVersion = $request->query->getString('version');
        $globalVersion = $versions['global'];

        if ($clientVersion !== '' && $clientVersion === $globalVersion) {
            return new JsonResponse([
                'success' => true,
                'changed' => false,
                'version' => $globalVersion,
                'versions' => $versions,
            ], headers: ['Cache-Control' => 'no-store']);
        }

        $payload = [
            'success' => true,
            'changed' => true,
            'version' => $globalVersion,
            'versions' => $versions,
        ];

        if ($request->query->getBoolean('include_data', false)) {
            if ($isStaffOrAdmin) {
                $orders = $this->liveSnapshot->getAllOrdersSorted();
            } else {
                $orders = $this->orderRepository->findBy(['createdBy' => $user], ['date' => 'DESC']);
            }

            $payload['data'] = [
                'orders' => $this->liveSnapshot->serializeOrders($orders),
                'products' => $this->serializeProducts(),
                'stocks' => $this->serializeStocks(),
            ];

            if ($isStaffOrAdmin) {
                $counts = $this->liveSnapshot->getDashboardCounts();
                $payload['data']['dashboard'] = [
                    'total_revenue' => $counts['revenue'],
                    'total_orders_week' => $counts['orders'],
                    'total_customers' => $counts['customers'],
                    'total_products' => $counts['products'],
                    'total_stock' => $counts['stock'],
                ];
            }
        }

        return new JsonResponse($payload, headers: ['Cache-Control' => 'no-store']);
    }

    private function serializeProducts(): array
    {
        $data = [];
        foreach ($this->productRepository->findAll() as $product) {
            $data[] = [
                'id' => $product->getId(),
                'name' => $product->getName(),
                'description' => $product->getDescription(),
                'price' => $product->getPrice(),
                'image' => $product->getImage(),
                'material' => $product->getMaterial(),
                'color' => $product->getColor(),
                'quantity' => $product->getQuantity(),
                'stockStatus' => $this->calculateProductStockStatus($product),
            ];
        }

        return $data;
    }

    private function calculateProductStockStatus(object $product): string
    {
        $totalQuantity = 0;
        $hasLowStock = false;
        $hasOutOfStock = false;

        foreach ($product->getStocks() as $stock) {
            $totalQuantity += $stock->getQuantity();
            $status = $stock->getStatus();
            if ($status === 'Out of Stock') {
                $hasOutOfStock = true;
            } elseif ($status === 'Low Stock') {
                $hasLowStock = true;
            }
        }

        $totalQuantity += $product->getQuantity();

        if ($hasOutOfStock || $totalQuantity === 0) {
            return 'Out of Stock';
        }
        if ($hasLowStock || $totalQuantity < 10) {
            return 'Low Stock';
        }

        return 'In Stock';
    }

    private function serializeStocks(): array
    {
        $data = [];
        foreach ($this->stockRepository->findAll() as $stock) {
            $data[] = [
                'id' => $stock->getId(),
                'quantity' => $stock->getQuantity(),
                'status' => $stock->getStatus(),
                'product_id' => $stock->getProduct()?->getId(),
            ];
        }

        return $data;
    }
}
