<?php

namespace App\Controller;

use App\Repository\OrderRepository;
use App\Repository\CustomerRepository;
use App\Repository\ProductRepository;
use App\Repository\StockRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/staff')]
final class StaffController extends AbstractController
{
    #[Route('/dashboard', name: 'app_staff_dashboard')]
    public function index(
        OrderRepository $orderRepository,
        CustomerRepository $customerRepository,
        ProductRepository $productRepository,
        StockRepository $stockRepository
    ): Response {
        $user = $this->getUser();
        
        // Get stats for staff's own records (handle nullable createdBy)
        $myProducts = $productRepository->createQueryBuilder('p')
            ->where('p.createdBy = :user OR p.createdBy IS NULL')
            ->setParameter('user', $user)
            ->getQuery()
            ->getResult();
        $myOrders = $orderRepository->createQueryBuilder('o')
            ->where('o.createdBy = :user OR o.createdBy IS NULL')
            ->setParameter('user', $user)
            ->getQuery()
            ->getResult();
        $myStocks = $stockRepository->createQueryBuilder('s')
            ->where('s.createdBy = :user OR s.createdBy IS NULL')
            ->setParameter('user', $user)
            ->getQuery()
            ->getResult();
        $myCustomers = $customerRepository->createQueryBuilder('c')
            ->where('c.createdBy = :user OR c.createdBy IS NULL')
            ->setParameter('user', $user)
            ->getQuery()
            ->getResult();
        
        // Get all records for viewing
        $allProducts = $productRepository->findAll();
        $allOrders = $orderRepository->findAll();
        $allStocks = $stockRepository->findAll();
        $allCustomers = $customerRepository->findAll();
        
        // Calculate stats
        $myProductsCount = count($myProducts);
        $myOrdersCount = count($myOrders);
        $myStocksCount = count($myStocks);
        $myCustomersCount = count($myCustomers);
        
        $totalProducts = count($allProducts);
        $totalOrders = count($allOrders);
        $totalStocks = count($allStocks);
        $totalCustomers = count($allCustomers);
        
        // Get recent activities
        $activities = $this->getRecentActivities(
            $orderRepository,
            $customerRepository,
            $productRepository,
            $stockRepository
        );
        
        // Prepare stats array
        $stats = [
            [
                'label' => 'My Products',
                'value' => number_format($myProductsCount),
                'desc' => 'Products I created',
                'icon' => 'fa-box',
                'trend' => 'neutral',
                'gradient' => 'from-orange-500 to-orange-600'
            ],
            [
                'label' => 'My Orders',
                'value' => number_format($myOrdersCount),
                'desc' => 'Orders I created',
                'icon' => 'fa-shopping-cart',
                'trend' => 'neutral',
                'gradient' => 'from-orange-400 to-orange-500'
            ],
            [
                'label' => 'My Stock',
                'value' => number_format($myStocksCount),
                'desc' => 'Stock records I created',
                'icon' => 'fa-warehouse',
                'trend' => 'neutral',
                'gradient' => 'from-orange-500 to-orange-600'
            ],
            [
                'label' => 'My Customers',
                'value' => number_format($myCustomersCount),
                'desc' => 'Customers I created',
                'icon' => 'fa-users',
                'trend' => 'neutral',
                'gradient' => 'from-orange-400 to-orange-500'
            ],
            [
                'label' => 'Total Records',
                'value' => number_format($totalProducts + $totalOrders + $totalStocks + $totalCustomers),
                'desc' => 'All records in system',
                'icon' => 'fa-database',
                'trend' => 'neutral',
                'gradient' => 'from-orange-500 to-orange-600'
            ]
        ];
        
        return $this->render('staff/dashboard.html.twig', [
            'stats' => $stats,
            'activities' => $activities,
        ]);
    }
    
    private function getRecentActivities(
        OrderRepository $orderRepository,
        CustomerRepository $customerRepository,
        ProductRepository $productRepository,
        StockRepository $stockRepository
    ): array {
        $activities = [];
        $user = $this->getUser();
        $oneWeekAgo = (new \DateTime())->modify('-7 days');
        
        // Get recent orders created by this staff
        $recentOrders = $orderRepository->createQueryBuilder('o')
            ->where('(o.createdBy = :user OR o.createdBy IS NULL)')
            ->andWhere('o.date >= :oneWeekAgo')
            ->setParameter('user', $user)
            ->setParameter('oneWeekAgo', $oneWeekAgo)
            ->orderBy('o.date', 'DESC')
            ->setMaxResults(5)
            ->getQuery()
            ->getResult();
        
        foreach ($recentOrders as $order) {
            $activities[] = [
                'icon' => 'fa-shopping-cart',
                'title' => 'Order created',
                'desc' => sprintf('Order #%d - %s', $order->getId(), $order->getProductName()),
                'time' => $this->timeAgo($order->getDate()),
                'timestamp' => $order->getDate(),
                'type' => 'order'
            ];
        }
        
        // Get recent products created by this staff
        $recentProducts = $productRepository->createQueryBuilder('p')
            ->where('p.createdBy = :user OR p.createdBy IS NULL')
            ->setParameter('user', $user)
            ->orderBy('p.id', 'DESC')
            ->setMaxResults(5)
            ->getQuery()
            ->getResult();
        
        foreach ($recentProducts as $product) {
            $activities[] = [
                'icon' => 'fa-box',
                'title' => 'Product created',
                'desc' => sprintf('%s - %s', $product->getName(), $product->getMaterial()),
                'time' => 'Recently added',
                'timestamp' => new \DateTime(),
                'type' => 'product'
            ];
        }
        
        // Sort by timestamp
        usort($activities, function($a, $b) {
            return $b['timestamp'] <=> $a['timestamp'];
        });
        
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

