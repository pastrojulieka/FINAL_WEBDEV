<?php

namespace App\Controller;

use App\Repository\CustomerRepository;
use App\Repository\OrderRepository;
use App\Repository\ProductRepository;
use App\Service\OrderLiveService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/live')]
final class LiveUpdateController extends AbstractController
{
    public function __construct(
        private OrderLiveService $orderLiveService,
    ) {
    }

    #[Route('/orders', name: 'app_live_orders', methods: ['GET'])]
    public function orders(Request $request): JsonResponse
    {
        $this->denyAccessUnlessGranted('ROLE_STAFF');

        $orders = $this->orderLiveService->getAllOrdersSorted();
        $version = $this->orderLiveService->computeFingerprint($orders);
        $clientVersion = $request->query->getString('version');

        if ($clientVersion !== '' && $clientVersion === $version) {
            return new JsonResponse([
                'success' => true,
                'changed' => false,
                'version' => $version,
                'count' => \count($orders),
            ]);
        }

        $html = $this->renderView('order/_cards.html.twig', [
            'orders' => $orders,
        ]);

        return new JsonResponse([
            'success' => true,
            'changed' => true,
            'version' => $version,
            'count' => \count($orders),
            'html' => $html,
            'orders' => $this->orderLiveService->serializeOrders($orders),
        ]);
    }

    #[Route('/dashboard', name: 'app_live_dashboard', methods: ['GET'])]
    public function dashboard(
        Request $request,
        OrderRepository $orderRepository,
        CustomerRepository $customerRepository,
        ProductRepository $productRepository,
    ): JsonResponse {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');

        $now = new \DateTime();
        $lastWeek = (clone $now)->modify('-7 days');
        $twoWeeksAgo = (clone $now)->modify('-14 days');

        $currentWeekRevenue = (float) ($orderRepository->createQueryBuilder('o')
            ->select('SUM(o.total_amount)')
            ->where('o.date >= :lastWeek')
            ->setParameter('lastWeek', $lastWeek)
            ->getQuery()
            ->getSingleScalarResult() ?? 0);

        $previousWeekRevenue = (float) ($orderRepository->createQueryBuilder('o')
            ->select('SUM(o.total_amount)')
            ->where('o.date >= :twoWeeks')
            ->andWhere('o.date < :lastWeek')
            ->setParameter('twoWeeks', $twoWeeksAgo)
            ->setParameter('lastWeek', $lastWeek)
            ->getQuery()
            ->getSingleScalarResult() ?? 1);

        $revenueChange = $previousWeekRevenue > 0
            ? (($currentWeekRevenue - $previousWeekRevenue) / $previousWeekRevenue) * 100
            : 0;

        $currentWeekOrders = (int) ($orderRepository->createQueryBuilder('o')
            ->select('COUNT(o.id)')
            ->where('o.date >= :lastWeek')
            ->setParameter('lastWeek', $lastWeek)
            ->getQuery()
            ->getSingleScalarResult() ?? 0);

        $previousWeekOrders = (int) ($orderRepository->createQueryBuilder('o')
            ->select('COUNT(o.id)')
            ->where('o.date >= :twoWeeks')
            ->andWhere('o.date < :lastWeek')
            ->setParameter('twoWeeks', $twoWeeksAgo)
            ->setParameter('lastWeek', $lastWeek)
            ->getQuery()
            ->getSingleScalarResult() ?? 1);

        $ordersChange = $previousWeekOrders > 0
            ? (($currentWeekOrders - $previousWeekOrders) / $previousWeekOrders) * 100
            : 0;

        $activities = $this->buildRecentActivities($orderRepository, $customerRepository, $productRepository);
        $orders = $this->orderLiveService->getAllOrdersSorted();
        $version = hash('sha256', $this->orderLiveService->computeFingerprint($orders).$currentWeekOrders.$currentWeekRevenue);

        $clientVersion = $request->query->getString('version');
        if ($clientVersion !== '' && $clientVersion === $version) {
            return new JsonResponse([
                'success' => true,
                'changed' => false,
                'version' => $version,
            ]);
        }

        $activitiesHtml = $this->renderView('live/_activities.html.twig', [
            'activities' => $activities,
        ]);

        return new JsonResponse([
            'success' => true,
            'changed' => true,
            'version' => $version,
            'stats' => [
                'totalRevenue' => '₱'.number_format($currentWeekRevenue, 2),
                'revenueDesc' => sprintf('%+.1f%% vs last week', $revenueChange),
                'totalOrders' => number_format($currentWeekOrders),
                'ordersDesc' => sprintf('%+.1f%% vs last week', $ordersChange),
            ],
            'activitiesHtml' => $activitiesHtml,
        ]);
    }

    #[Route('/staff-dashboard', name: 'app_live_staff_dashboard', methods: ['GET'])]
    public function staffDashboard(
        Request $request,
        OrderRepository $orderRepository,
        CustomerRepository $customerRepository,
        ProductRepository $productRepository,
    ): JsonResponse {
        $this->denyAccessUnlessGranted('ROLE_STAFF');

        $user = $this->getUser();
        $myOrdersCount = (int) $orderRepository->createQueryBuilder('o')
            ->select('COUNT(o.id)')
            ->where('o.createdBy = :user OR o.createdBy IS NULL')
            ->setParameter('user', $user)
            ->getQuery()
            ->getSingleScalarResult();

        $totalOrders = (int) $orderRepository->createQueryBuilder('o')
            ->select('COUNT(o.id)')
            ->getQuery()
            ->getSingleScalarResult();

        $activities = $this->buildRecentActivities($orderRepository, $customerRepository, $productRepository);
        $orders = $this->orderLiveService->getAllOrdersSorted();
        $version = hash('sha256', $this->orderLiveService->computeFingerprint($orders).$myOrdersCount.$totalOrders);

        $clientVersion = $request->query->getString('version');
        if ($clientVersion !== '' && $clientVersion === $version) {
            return new JsonResponse([
                'success' => true,
                'changed' => false,
                'version' => $version,
            ]);
        }

        $activitiesHtml = $this->renderView('live/_activities.html.twig', [
            'activities' => $activities,
        ]);

        return new JsonResponse([
            'success' => true,
            'changed' => true,
            'version' => $version,
            'stats' => [
                'myOrders' => number_format($myOrdersCount),
                'totalOrders' => number_format($totalOrders),
            ],
            'activitiesHtml' => $activitiesHtml,
        ]);
    }

    private function buildRecentActivities(
        OrderRepository $orderRepository,
        CustomerRepository $customerRepository,
        ProductRepository $productRepository,
    ): array {
        $activities = [];
        $oneWeekAgo = (new \DateTime())->modify('-7 days');

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
            ];
        }

        $recentCustomers = $customerRepository->createQueryBuilder('c')
            ->orderBy('c.id', 'DESC')
            ->setMaxResults(5)
            ->getQuery()
            ->getResult();

        foreach ($recentCustomers as $customer) {
            $activities[] = [
                'icon' => 'fa-user-plus',
                'title' => 'New customer registered',
                'desc' => sprintf('%s joined the platform', $customer->getName()),
                'time' => 'Recently added',
            ];
        }

        $recentProducts = $productRepository->createQueryBuilder('p')
            ->orderBy('p.id', 'DESC')
            ->setMaxResults(5)
            ->getQuery()
            ->getResult();

        foreach ($recentProducts as $product) {
            $activities[] = [
                'icon' => 'fa-box',
                'title' => 'New product added',
                'desc' => sprintf('%s - %s', $product->getName(), $product->getMaterial()),
                'time' => 'Recently added',
            ];
        }

        return \array_slice($activities, 0, 8);
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
}
