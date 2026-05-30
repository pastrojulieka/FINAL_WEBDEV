<?php

namespace App\Controller;

use App\Repository\ActivityLogRepository;
use App\Repository\CustomerRepository;
use App\Repository\OrderRepository;
use App\Repository\ProductRepository;
use App\Repository\StockRepository;
use App\Repository\UserRepository;
use App\Service\LiveSnapshotService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/live')]
final class LiveUpdateController extends AbstractController
{
    public function __construct(
        private LiveSnapshotService $liveSnapshot,
    ) {
    }

    #[Route('/orders', name: 'app_live_orders', methods: ['GET'])]
    public function orders(Request $request): JsonResponse
    {
        $this->denyAccessUnlessGranted('ROLE_STAFF');

        $version = $this->liveSnapshot->fingerprintOrders();
        if ($this->isUnchanged($request, $version)) {
            return $this->unchanged($version, ['count' => \count($this->liveSnapshot->getAllOrdersSorted())]);
        }

        $orders = $this->liveSnapshot->getAllOrdersSorted();

        return new JsonResponse([
            'success' => true,
            'changed' => true,
            'version' => $version,
            'count' => \count($orders),
            'html' => $this->renderView('order/_cards.html.twig', ['orders' => $orders]),
            'orders' => $this->liveSnapshot->serializeOrders($orders),
        ]);
    }

    #[Route('/dashboard', name: 'app_live_dashboard', methods: ['GET'])]
    public function dashboard(Request $request, OrderRepository $orderRepository): JsonResponse
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');

        $version = $this->liveSnapshot->fingerprintDashboard();
        if ($this->isUnchanged($request, $version)) {
            return $this->unchanged($version);
        }

        return new JsonResponse([
            'success' => true,
            'changed' => true,
            'version' => $version,
            'stats' => $this->liveSnapshot->buildAdminDashboardStats($orderRepository),
            'activitiesHtml' => $this->renderView('live/_activities.html.twig', [
                'activities' => $this->liveSnapshot->buildActivityFeed(8),
            ]),
        ]);
    }

    #[Route('/staff-dashboard', name: 'app_live_staff_dashboard', methods: ['GET'])]
    public function staffDashboard(
        Request $request,
        OrderRepository $orderRepository,
        ProductRepository $productRepository,
        StockRepository $stockRepository,
        CustomerRepository $customerRepository,
    ): JsonResponse {
        $this->denyAccessUnlessGranted('ROLE_STAFF');

        $user = $this->getUser();

        $myProductsCount = (int) $productRepository->createQueryBuilder('p')
            ->select('COUNT(p.id)')
            ->where('p.createdBy = :user OR p.createdBy IS NULL')
            ->setParameter('user', $user)
            ->getQuery()
            ->getSingleScalarResult();

        $myOrdersCount = (int) $orderRepository->createQueryBuilder('o')
            ->select('COUNT(o.id)')
            ->where('o.createdBy = :user OR o.createdBy IS NULL')
            ->setParameter('user', $user)
            ->getQuery()
            ->getSingleScalarResult();

        $myStocksCount = (int) $stockRepository->createQueryBuilder('s')
            ->select('COUNT(s.id)')
            ->where('s.createdBy = :user OR s.createdBy IS NULL')
            ->setParameter('user', $user)
            ->getQuery()
            ->getSingleScalarResult();

        $myCustomersCount = (int) $customerRepository->createQueryBuilder('c')
            ->select('COUNT(c.id)')
            ->where('c.createdBy = :user OR c.createdBy IS NULL')
            ->setParameter('user', $user)
            ->getQuery()
            ->getSingleScalarResult();

        $totalRecords = (int) $productRepository->createQueryBuilder('p')->select('COUNT(p.id)')->getQuery()->getSingleScalarResult()
            + (int) $orderRepository->createQueryBuilder('o')->select('COUNT(o.id)')->getQuery()->getSingleScalarResult()
            + (int) $stockRepository->createQueryBuilder('s')->select('COUNT(s.id)')->getQuery()->getSingleScalarResult()
            + (int) $customerRepository->createQueryBuilder('c')->select('COUNT(c.id)')->getQuery()->getSingleScalarResult();

        $version = hash('sha256', implode('|', [
            $this->liveSnapshot->fingerprintDashboard(),
            $myProductsCount,
            $myOrdersCount,
            $myStocksCount,
            $myCustomersCount,
            $totalRecords,
        ]));

        if ($this->isUnchanged($request, $version)) {
            return $this->unchanged($version);
        }

        return new JsonResponse([
            'success' => true,
            'changed' => true,
            'version' => $version,
            'stats' => [
                'myProducts' => number_format($myProductsCount),
                'myOrders' => number_format($myOrdersCount),
                'myStock' => number_format($myStocksCount),
                'myCustomers' => number_format($myCustomersCount),
                'totalRecords' => number_format($totalRecords),
            ],
            'activitiesHtml' => $this->renderView('live/_activities.html.twig', [
                'activities' => $this->liveSnapshot->buildActivityFeed(8),
            ]),
        ]);
    }

    #[Route('/products', name: 'app_live_products', methods: ['GET'])]
    public function products(Request $request, ProductRepository $productRepository): JsonResponse
    {
        $this->denyAccessUnlessGranted('ROLE_STAFF');

        $version = $this->liveSnapshot->fingerprintProducts();
        $productsWithStatus = $this->liveSnapshot->getProductsWithStatus();

        if ($this->isUnchanged($request, $version)) {
            return $this->unchanged($version, ['count' => \count($productsWithStatus)]);
        }

        return new JsonResponse([
            'success' => true,
            'changed' => true,
            'version' => $version,
            'count' => \count($productsWithStatus),
            'html' => $this->renderView('product/_grid_inner.html.twig', [
                'productsWithStatus' => $productsWithStatus,
            ]),
        ]);
    }

    #[Route('/customers', name: 'app_live_customers', methods: ['GET'])]
    public function customers(Request $request, CustomerRepository $customerRepository): JsonResponse
    {
        $this->denyAccessUnlessGranted('ROLE_STAFF');

        $version = $this->liveSnapshot->fingerprintCustomers();
        $customers = $customerRepository->findAll();

        if ($this->isUnchanged($request, $version)) {
            return $this->unchanged($version, ['count' => \count($customers)]);
        }

        return new JsonResponse([
            'success' => true,
            'changed' => true,
            'version' => $version,
            'count' => \count($customers),
            'html' => $this->renderView('customer/_grid_inner.html.twig', ['customers' => $customers]),
        ]);
    }

    #[Route('/users', name: 'app_live_users', methods: ['GET'])]
    public function users(Request $request, UserRepository $userRepository): JsonResponse
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');

        $version = $this->liveSnapshot->fingerprintUsers();
        $users = $userRepository->findBy([], ['id' => 'DESC'], 500);

        if ($this->isUnchanged($request, $version)) {
            return $this->unchanged($version, ['count' => \count($users)]);
        }

        return new JsonResponse([
            'success' => true,
            'changed' => true,
            'version' => $version,
            'count' => \count($users),
            'html' => $this->renderView('admin/users/_table_body.html.twig', ['users' => $users]),
        ]);
    }

    #[Route('/activity-logs', name: 'app_live_activity_logs', methods: ['GET'])]
    public function activityLogs(Request $request, ActivityLogRepository $activityLogRepository): JsonResponse
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');

        $search = $request->query->getString('search');
        $logs = $search !== ''
            ? $activityLogRepository->searchLogs($search, 500)
            : $activityLogRepository->findRecent(500);

        $version = $this->liveSnapshot->fingerprintActivityLogs();

        if ($this->isUnchanged($request, $version)) {
            return $this->unchanged($version, ['count' => \count($logs)]);
        }

        return new JsonResponse([
            'success' => true,
            'changed' => true,
            'version' => $version,
            'count' => \count($logs),
            'html' => $this->renderView('admin/activity_logs/_rows.html.twig', ['logs' => $logs]),
        ]);
    }

    private function isUnchanged(Request $request, string $version): bool
    {
        $clientVersion = $request->query->getString('version');

        return $clientVersion !== '' && $clientVersion === $version;
    }

    private function unchanged(string $version, array $extra = []): JsonResponse
    {
        return new JsonResponse(array_merge([
            'success' => true,
            'changed' => false,
            'version' => $version,
        ], $extra));
    }
}
