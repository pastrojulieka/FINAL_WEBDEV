<?php

namespace App\Controller;

use App\Repository\OrderRepository;
use App\Repository\CustomerRepository;
use App\Repository\ProductRepository;
use App\Repository\StockRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

final class DashboardController extends AbstractController
{
    #[Route('/dashboard', name: 'app_dashboard')]
    public function index(
        OrderRepository $orderRepository,
        CustomerRepository $customerRepository,
        ProductRepository $productRepository,
        StockRepository $stockRepository
    ): Response {
        // Calculate date ranges
        $now = new \DateTime();
        $lastWeek = (clone $now)->modify('-7 days');
        $twoWeeksAgo = (clone $now)->modify('-14 days');

        // Total Revenue (current week)
        $currentWeekRevenue = $orderRepository->createQueryBuilder('o')
            ->select('SUM(o.total_amount)')
            ->where('o.date >= :lastWeek')
            ->setParameter('lastWeek', $lastWeek)
            ->getQuery()
            ->getSingleScalarResult() ?? 0;

        // Previous week revenue for comparison
        $previousWeekRevenue = $orderRepository->createQueryBuilder('o')
            ->select('SUM(o.total_amount)')
            ->where('o.date >= :twoWeeks')
            ->andWhere('o.date < :lastWeek')
            ->setParameter('twoWeeks', $twoWeeksAgo)
            ->setParameter('lastWeek', $lastWeek)
            ->getQuery()
            ->getSingleScalarResult() ?? 1;

        // Calculate revenue percentage change
        $revenueChange = $previousWeekRevenue > 0 
            ? (($currentWeekRevenue - $previousWeekRevenue) / $previousWeekRevenue) * 100 
            : 0;

        // Total Orders (current week)
        $currentWeekOrders = $orderRepository->createQueryBuilder('o')
            ->select('COUNT(o.id)')
            ->where('o.date >= :lastWeek')
            ->setParameter('lastWeek', $lastWeek)
            ->getQuery()
            ->getSingleScalarResult() ?? 0;

        // Previous week orders
        $previousWeekOrders = $orderRepository->createQueryBuilder('o')
            ->select('COUNT(o.id)')
            ->where('o.date >= :twoWeeks')
            ->andWhere('o.date < :lastWeek')
            ->setParameter('twoWeeks', $twoWeeksAgo)
            ->setParameter('lastWeek', $lastWeek)
            ->getQuery()
            ->getSingleScalarResult() ?? 1;

        // Calculate orders percentage change
        $ordersChange = $previousWeekOrders > 0 
            ? (($currentWeekOrders - $previousWeekOrders) / $previousWeekOrders) * 100 
            : 0;

        // Total Customers (all time)
        $totalCustomers = $customerRepository->createQueryBuilder('c')
            ->select('COUNT(c.id)')
            ->getQuery()
            ->getSingleScalarResult() ?? 0;

        // Total Products (all time)
        $totalProducts = $productRepository->createQueryBuilder('p')
            ->select('COUNT(p.id)')
            ->getQuery()
            ->getSingleScalarResult() ?? 0;

        // Current stock on hand (count stock records)
        $totalStock = (int) ($stockRepository->createQueryBuilder('s')
            ->select('COUNT(s.id)')
            ->getQuery()
            ->getSingleScalarResult() ?? 0);

        // Get recent activities from all sources
        $activities = $this->getRecentActivities(
            $orderRepository,
            $customerRepository,
            $productRepository
        );

        // Prepare stats array
        $stats = [
            [
                'label' => 'Total Revenue',
                'value' => '₱' . number_format($currentWeekRevenue, 2),
                'desc' => sprintf('%+.1f%% vs last week', $revenueChange),
                'icon' => 'fa-peso-sign',
                'trend' => $revenueChange >= 0 ? 'up' : 'down',
                'gradient' => 'from-orange-500 to-orange-600'
            ],
            [
                'label' => 'Total Orders',
                'value' => number_format($currentWeekOrders),
                'desc' => sprintf('%+.1f%% vs last week', $ordersChange),
                'icon' => 'fa-shopping-cart',
                'trend' => $ordersChange >= 0 ? 'up' : 'down',
                'gradient' => 'from-orange-400 to-orange-500'
            ],
            [
                'label' => 'Stock',
                'value' => number_format($totalStock),
                'desc' => 'Stock records available',
                'icon' => 'fa-warehouse',
                'trend' => 'neutral',
                'gradient' => 'from-orange-500 to-orange-600'
            ],
            [
                'label' => 'Total Customers',
                'value' => number_format($totalCustomers),
                'desc' => 'All registered customers',
                'icon' => 'fa-users',
                'trend' => 'neutral',
                'gradient' => 'from-orange-500 to-orange-600'
            ],
            [
                'label' => 'Total Products',
                'value' => number_format($totalProducts),
                'desc' => 'Available in inventory',
                'icon' => 'fa-box',
                'trend' => 'neutral',
                'gradient' => 'from-orange-400 to-orange-500'
            ]
        ];

        return $this->render('dashboard/index.html.twig', [
            'stats' => $stats,
            'activities' => $activities,
        ]);
    }

    #[Route('/search', name: 'app_search')]
    public function search(
        Request $request,
        ProductRepository $productRepo,
        OrderRepository $orderRepo,
        CustomerRepository $customerRepo
    ): Response {
        $query = $request->query->get('q', '');
        
        $products = [];
        $orders = [];
        $customers = [];
        
        if (!empty($query)) {
            $products = $productRepo->search($query);
            $orders = $orderRepo->search($query);
            $customers = $customerRepo->search($query);
        }
        
        return $this->render('search/index.html.twig', [
            'query' => $query,
            'products' => $products,
            'orders' => $orders,
            'customers' => $customers,
        ]);
    }

    private function getRecentActivities(
        OrderRepository $orderRepository,
        CustomerRepository $customerRepository,
        ProductRepository $productRepository
    ): array {
        $activities = [];
        $oneWeekAgo = (new \DateTime())->modify('-7 days');

        // Get recent orders (last 7 days)
        $recentOrders = $orderRepository->createQueryBuilder('o')
            ->where('o.date >= :oneWeekAgo')
            ->setParameter('oneWeekAgo', $oneWeekAgo)
            ->orderBy('o.date', 'DESC')
            ->setMaxResults(10)
            ->getQuery()
            ->getResult();

        foreach ($recentOrders as $order) {
            $activities[] = [
                'icon' => 'fa-shopping-cart',
                'title' => 'Order received',
                'desc' => sprintf(
                    'Order #%d - %s from %s',
                    $order->getId(),
                    $order->getProductName(),
                    $order->getCustomerName()
                ),
                'time' => $this->timeAgo($order->getDate()),
                'color' => 'orange',
                'timestamp' => $order->getDate(),
                'type' => 'order'
            ];
        }

        // Get all customers and sort by ID (newest first)
        $recentCustomers = $customerRepository->createQueryBuilder('c')
            ->orderBy('c.id', 'DESC')
            ->setMaxResults(5)
            ->getQuery()
            ->getResult();

        foreach ($recentCustomers as $customer) {
            // Estimate creation time based on ID (for display purposes)
            $estimatedDate = (new \DateTime())->modify('-' . (100 - $customer->getId()) . ' hours');
            
            $activities[] = [
                'icon' => 'fa-user-plus',
                'title' => 'New customer registered',
                'desc' => sprintf('%s joined the platform', $customer->getName()),
                'time' => 'Recently added',
                'color' => 'orange',
                'timestamp' => $estimatedDate,
                'type' => 'customer'
            ];
        }

        // Get all products and sort by ID (newest first)
        $recentProducts = $productRepository->createQueryBuilder('p')
            ->orderBy('p.id', 'DESC')
            ->setMaxResults(5)
            ->getQuery()
            ->getResult();

        foreach ($recentProducts as $product) {
            // Estimate creation time based on ID (for display purposes)
            $estimatedDate = (new \DateTime())->modify('-' . (100 - $product->getId()) . ' hours');
            
            $activities[] = [
                'icon' => 'fa-box',
                'title' => 'New product added',
                'desc' => sprintf('%s - %s', $product->getName(), $product->getMaterial()),
                'time' => 'Recently added',
                'color' => 'orange',
                'timestamp' => $estimatedDate,
                'type' => 'product'
            ];
        }

        // Sort all activities by timestamp (most recent first)
        usort($activities, function($a, $b) {
            return $b['timestamp'] <=> $a['timestamp'];
        });

        // Return only the 8 most recent activities
        return array_slice($activities, 0, 8);
    }

    private function timeAgo(\DateTime $datetime): string
    {
        $now = new \DateTime();
        $diff = $now->diff($datetime);

        if ($diff->y > 0) {
            return $diff->y . ' year' . ($diff->y > 1 ? 's' : '') . ' ago';
        }
        if ($diff->m > 0) {
            return $diff->m . ' month' . ($diff->m > 1 ? 's' : '') . ' ago';
        }
        if ($diff->d > 0) {
            return $diff->d . ' day' . ($diff->d > 1 ? 's' : '') . ' ago';
        }
        if ($diff->h > 0) {
            return $diff->h . ' hour' . ($diff->h > 1 ? 's' : '') . ' ago';
        }
        if ($diff->i > 0) {
            return $diff->i . ' minute' . ($diff->i > 1 ? 's' : '') . ' ago';
        }
        return 'just now';
    }
}