<?php

namespace App\Service;

use App\Entity\ActivityLog;
use App\Entity\Order;
use App\Entity\User;
use App\Repository\ActivityLogRepository;
use App\Repository\CustomerRepository;
use App\Repository\OrderRepository;
use App\Repository\ProductRepository;
use App\Repository\StockRepository;
use App\Repository\UserRepository;

class LiveSnapshotService
{
    public function __construct(
        private OrderRepository $orderRepository,
        private ProductRepository $productRepository,
        private CustomerRepository $customerRepository,
        private UserRepository $userRepository,
        private StockRepository $stockRepository,
        private ActivityLogRepository $activityLogRepository,
        private OrderLiveService $orderLiveService,
    ) {
    }

    public function fingerprintOrders(): string
    {
        return $this->orderLiveService->computeFingerprint($this->orderLiveService->getAllOrdersSorted());
    }

    public function fingerprintProducts(): string
    {
        $products = $this->productRepository->findBy([], ['id' => 'DESC']);
        if ($products === []) {
            return 'products:empty';
        }

        $parts = [];
        foreach ($products as $p) {
            $parts[] = sprintf('%d:%s:%s:%s', $p->getId(), $p->getPrice(), $p->getQuantity(), $p->getName());
        }

        return hash('sha256', 'products|'.implode('|', $parts));
    }

    public function fingerprintCustomers(): string
    {
        $customers = $this->customerRepository->findBy([], ['id' => 'DESC']);
        if ($customers === []) {
            return 'customers:empty';
        }

        $parts = [];
        foreach ($customers as $c) {
            $parts[] = sprintf('%d:%s', $c->getId(), $c->getName());
        }

        return hash('sha256', 'customers|'.implode('|', $parts));
    }

    public function fingerprintUsers(): string
    {
        $users = $this->userRepository->findBy([], ['id' => 'DESC'], 500);
        if ($users === []) {
            return 'users:empty';
        }

        $parts = [];
        foreach ($users as $u) {
            $parts[] = sprintf('%d:%s:%s', $u->getId(), $u->getEmail(), implode(',', $u->getRoles()));
        }

        return hash('sha256', 'users|'.implode('|', $parts));
    }

    public function fingerprintActivityLogs(): string
    {
        $logs = $this->activityLogRepository->findRecent(500);
        if ($logs === []) {
            return 'logs:empty';
        }

        $parts = [];
        foreach ($logs as $log) {
            $parts[] = sprintf(
                '%d:%s:%s:%s',
                $log->getId(),
                $log->getAction(),
                $log->getSubject(),
                $log->getCreatedAt()?->format('Y-m-d H:i:s') ?? ''
            );
        }

        return hash('sha256', 'logs|'.implode('|', $parts));
    }

    public function fingerprintStocks(): string
    {
        $stocks = $this->stockRepository->findAll();
        if ($stocks === []) {
            return 'stocks:empty';
        }

        $parts = [];
        foreach ($stocks as $s) {
            $parts[] = sprintf('%d:%d:%s', $s->getId(), $s->getQuantity(), $s->getStatus());
        }

        return hash('sha256', 'stocks|'.implode('|', $parts));
    }

    /**
     * @return array{revenue: float, orders: int, customers: int, products: int, stock: int}
     */
    public function getDashboardCounts(): array
    {
        $now = new \DateTime();
        $lastWeek = (clone $now)->modify('-7 days');

        $revenue = $this->orderRepository->sumCompletedRevenueSince($lastWeek);
        $orders = $this->orderRepository->countOrdersSince($lastWeek);

        $customers = (int) ($this->customerRepository->createQueryBuilder('c')
            ->select('COUNT(c.id)')
            ->getQuery()
            ->getSingleScalarResult() ?? 0);

        $products = (int) ($this->productRepository->createQueryBuilder('p')
            ->select('COUNT(p.id)')
            ->getQuery()
            ->getSingleScalarResult() ?? 0);

        $stock = (int) ($this->stockRepository->createQueryBuilder('s')
            ->select('COUNT(s.id)')
            ->getQuery()
            ->getSingleScalarResult() ?? 0);

        return [
            'revenue' => $revenue,
            'orders' => $orders,
            'customers' => $customers,
            'products' => $products,
            'stock' => $stock,
        ];
    }

    /**
     * Stats payload for live admin dashboard polling and instant UI updates.
     *
     * @return array<string, string>
     */
    public function buildAdminDashboardStats(OrderRepository $orderRepository): array
    {
        $counts = $this->getDashboardCounts();
        $now = new \DateTime();
        $lastWeek = (clone $now)->modify('-7 days');
        $twoWeeksAgo = (clone $now)->modify('-14 days');

        $previousWeekRevenue = $orderRepository->sumCompletedRevenueBetween($twoWeeksAgo, $lastWeek);
        if ($previousWeekRevenue <= 0) {
            $previousWeekRevenue = 1;
        }

        $previousWeekOrders = $orderRepository->countOrdersBetween($twoWeeksAgo, $lastWeek);
        if ($previousWeekOrders <= 0) {
            $previousWeekOrders = 1;
        }

        $revenueChange = (($counts['revenue'] - $previousWeekRevenue) / $previousWeekRevenue) * 100;
        $ordersChange = (($counts['orders'] - $previousWeekOrders) / $previousWeekOrders) * 100;

        return [
            'totalRevenue' => '₱'.number_format($counts['revenue'], 2),
            'revenueDesc' => sprintf('%+.1f%% vs last week · completed', $revenueChange),
            'totalOrders' => number_format($counts['orders']),
            'ordersDesc' => sprintf('%+.1f%% vs last week', $ordersChange),
            'totalCustomers' => number_format($counts['customers']),
            'totalProducts' => number_format($counts['products']),
            'totalStock' => number_format($counts['stock']),
        ];
    }

    public function fingerprintDashboard(): string
    {
        $counts = $this->getDashboardCounts();

        return hash('sha256', implode('|', [
            $this->fingerprintOrders(),
            $this->fingerprintProducts(),
            $this->fingerprintCustomers(),
            $this->fingerprintUsers(),
            $this->fingerprintActivityLogs(),
            $this->fingerprintStocks(),
            (string) $counts['revenue'],
            (string) $counts['orders'],
            (string) $counts['customers'],
            (string) $counts['products'],
            (string) $counts['stock'],
        ]));
    }

    /**
     * Global fingerprint for mobile /api/sync.
     */
    public function fingerprintGlobal(?User $user = null): string
    {
        $parts = [
            $this->fingerprintOrders(),
            $this->fingerprintProducts(),
            $this->fingerprintStocks(),
            $this->fingerprintActivityLogs(),
        ];

        if ($user) {
            $roles = $user->getRoles();
            if (\in_array('ROLE_ADMIN', $roles, true) || \in_array('ROLE_STAFF', $roles, true)) {
                $parts[] = $this->fingerprintCustomers();
                $parts[] = $this->fingerprintUsers();
                $parts[] = $this->fingerprintDashboard();
            } else {
                $myOrders = $this->orderRepository->findBy(['createdBy' => $user], ['date' => 'DESC']);
                $parts[] = $this->orderLiveService->computeFingerprint($myOrders);
            }
        }

        return hash('sha256', implode('|', $parts));
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function buildActivityFeed(int $limit = 8): array
    {
        $activities = [];
        $logs = $this->activityLogRepository->findRecent($limit);

        foreach ($logs as $log) {
            $activities[] = $this->activityFromLog($log);
        }

        if (\count($activities) < $limit) {
            $oneWeekAgo = (new \DateTime())->modify('-7 days');
            $recentOrders = $this->orderRepository->createQueryBuilder('o')
                ->where('o.date >= :oneWeekAgo')
                ->setParameter('oneWeekAgo', $oneWeekAgo)
                ->orderBy('o.date', 'DESC')
                ->setMaxResults($limit)
                ->getQuery()
                ->getResult();

            foreach ($recentOrders as $order) {
                if (\count($activities) >= $limit) {
                    break;
                }
                $activities[] = [
                    'icon' => 'fa-shopping-cart',
                    'title' => 'Order received',
                    'desc' => sprintf('Order #%d - %s from %s', $order->getId(), $order->getProductName(), $order->getCustomerName()),
                    'time' => $this->timeAgo($order->getDate()),
                ];
            }
        }

        return \array_slice($activities, 0, $limit);
    }

    /**
     * @return array{icon: string, title: string, desc: string, time: string}
     */
    private function activityFromLog(ActivityLog $log): array
    {
        $action = $log->getAction();
        $icon = match ($action) {
            ActivityLog::ACTION_LOGIN => 'fa-right-to-bracket',
            ActivityLog::ACTION_LOGOUT => 'fa-right-from-bracket',
            ActivityLog::ACTION_CREATE => 'fa-plus-circle',
            ActivityLog::ACTION_UPDATE => 'fa-pen',
            ActivityLog::ACTION_DELETE => 'fa-trash',
            default => 'fa-circle-info',
        };

        $title = match ($action) {
            ActivityLog::ACTION_LOGIN => 'User logged in',
            ActivityLog::ACTION_LOGOUT => 'User logged out',
            ActivityLog::ACTION_CREATE => sprintf('%s created', $log->getSubject() ?? 'Record'),
            ActivityLog::ACTION_UPDATE => sprintf('%s updated', $log->getSubject() ?? 'Record'),
            ActivityLog::ACTION_DELETE => sprintf('%s deleted', $log->getSubject() ?? 'Record'),
            default => $action ?? 'Activity',
        };

        $desc = $log->getDetails() ?? sprintf(
            '%s %s #%s',
            $log->getUserEmail() ?? 'System',
            $action,
            $log->getSubjectId() ?? '-'
        );

        return [
            'icon' => $icon,
            'title' => $title,
            'desc' => $desc,
            'time' => $log->getCreatedAt() ? $this->timeAgoFromImmutable($log->getCreatedAt()) : 'just now',
        ];
    }

    private function timeAgoFromImmutable(\DateTimeImmutable $datetime): string
    {
        return $this->timeAgo(\DateTime::createFromImmutable($datetime));
    }

    private function timeAgo(\DateTime $datetime): string
    {
        $now = new \DateTime();
        $diff = $now->diff($datetime);

        if ($diff->d > 0) {
            return $diff->d.' day'.($diff->d > 1 ? 's' : '').' ago';
        }
        if ($diff->h > 0) {
            return $diff->h.' hour'.($diff->h > 1 ? 's' : '').' ago';
        }
        if ($diff->i > 0) {
            return $diff->i.' minute'.($diff->i > 1 ? 's' : '').' ago';
        }

        return 'just now';
    }

    /**
     * @param Order[] $orders
     */
    public function serializeOrders(array $orders): array
    {
        return $this->orderLiveService->serializeOrders($orders);
    }

    public function getAllOrdersSorted(): array
    {
        return $this->orderLiveService->getAllOrdersSorted();
    }

    /**
     * @return array<int, array{product: object, stockStatus: string}>
     */
    public function getProductsWithStatus(): array
    {
        $result = [];
        foreach ($this->productRepository->findAll() as $product) {
            $stocks = $product->getStocks();
            $totalQuantity = $product->getQuantity();
            $hasLowStock = false;
            $hasOutOfStock = false;

            foreach ($stocks as $stock) {
                $totalQuantity += $stock->getQuantity();
                if ($stock->getStatus() === 'Out of Stock') {
                    $hasOutOfStock = true;
                } elseif ($stock->getStatus() === 'Low Stock') {
                    $hasLowStock = true;
                }
            }

            if ($hasOutOfStock || $totalQuantity === 0) {
                $status = 'Out of Stock';
            } elseif ($hasLowStock || $totalQuantity < 10) {
                $status = 'Low Stock';
            } else {
                $status = 'In Stock';
            }

            $result[] = ['product' => $product, 'stockStatus' => $status];
        }

        return $result;
    }
}
