<?php

namespace App\Controller;

use App\Entity\Order;
use App\Entity\Product;
use App\Entity\User;
use App\Repository\OrderRepository;
use App\Repository\ProductRepository;
use App\Repository\StockRepository;
use App\Repository\UserRepository;
use App\Service\OrderLiveService;
use App\Service\PushNotificationService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api')]
class ApiOrderController extends AbstractController
{
    public function __construct(
        private OrderLiveService $orderLiveService,
        private PushNotificationService $pushNotificationService,
        private UserRepository $userRepository,
    ) {
    }

    #[Route('/orders', name: 'api_orders', methods: ['GET'])]
    public function getOrders(Request $request, OrderRepository $orderRepository): JsonResponse
    {
        $user = $this->getUser();

        if (!$user) {
            return new JsonResponse([
                'success' => false,
                'message' => 'Authentication required',
            ], 401);
        }

        /** @var User $user */
        $roles = $user->getRoles();
        $isStaffOrAdmin = in_array('ROLE_STAFF', $roles) || in_array('ROLE_ADMIN', $roles);

        if ($isStaffOrAdmin) {
            $orders = $orderRepository->findBy([], ['date' => 'DESC']);
        } else {
            $orders = $orderRepository->findBy(['createdBy' => $user], ['date' => 'DESC']);
        }

        $version = $this->orderLiveService->computeFingerprint($orders);
        $clientVersion = $request->query->getString('version');

        if ($clientVersion !== '' && $clientVersion === $version) {
            return new JsonResponse([
                'success' => true,
                'changed' => false,
                'version' => $version,
                'count' => \count($orders),
            ], headers: ['Cache-Control' => 'no-store, no-cache, must-revalidate']);
        }

        return new JsonResponse([
            'success' => true,
            'changed' => true,
            'version' => $version,
            'count' => \count($orders),
            'data' => array_map([$this, 'serializeOrder'], $orders),
        ], headers: ['Cache-Control' => 'no-store, no-cache, must-revalidate']);
    }

    #[Route('/orders/{id}', name: 'api_order_show', requirements: ['id' => '\d+'], methods: ['GET'])]
    public function getOrder(int $id, OrderRepository $orderRepository): JsonResponse
    {
        $user = $this->getUser();

        if (!$user) {
            return new JsonResponse([
                'success' => false,
                'message' => 'Authentication required',
            ], 401);
        }

        $order = $orderRepository->find($id);

        if (!$order) {
            return new JsonResponse([
                'success' => false,
                'message' => 'Order not found',
            ], 404);
        }

        if (!$this->canAccessOrder($user, $order)) {
            return new JsonResponse([
                'success' => false,
                'message' => 'Access denied',
            ], 403);
        }

        return new JsonResponse([
            'success' => true,
            'data' => $this->serializeOrder($order),
        ], headers: ['Cache-Control' => 'no-store, no-cache, must-revalidate']);
    }

    #[Route('/orders', name: 'api_create_order', methods: ['POST'])]
    public function createOrder(Request $request, EntityManagerInterface $entityManager, ProductRepository $productRepository, StockRepository $stockRepository): JsonResponse
    {
        try {
            $user = $this->getUser();

            if (!$user) {
                return new JsonResponse([
                    'success' => false,
                    'message' => 'Authentication required',
                ], 401);
            }

            $data = json_decode($request->getContent(), true);

            if (!$data || !isset($data['product_id'], $data['quantity'], $data['customer_name'])) {
                return new JsonResponse([
                    'success' => false,
                    'message' => 'Product ID, quantity, and customer_name are required',
                ], 400);
            }

            if ((int) $data['quantity'] <= 0) {
                return new JsonResponse([
                    'success' => false,
                    'message' => 'Quantity must be greater than 0',
                ], 400);
            }

            $product = $productRepository->find($data['product_id']);

            if (!$product) {
                return new JsonResponse([
                    'success' => false,
                    'message' => 'Product not found',
                ], 404);
            }

            $stocks = $stockRepository->findBy(['product' => $product]);
            $totalStock = array_sum(array_map(fn ($s) => $s->getQuantity(), $stocks));
            if (count($stocks) === 0) {
                $totalStock = $product->getQuantity();
            }

            if ($totalStock < $data['quantity']) {
                return new JsonResponse([
                    'success' => false,
                    'message' => 'Insufficient stock available. Available: '.$totalStock.', Requested: '.$data['quantity'],
                ], 400);
            }

            $order = $this->buildOrderFromProduct($product, $data, $user);

            $product->setQuantity($product->getQuantity() - (int) $data['quantity']);

            $entityManager->persist($order);
            $entityManager->flush();

            return new JsonResponse([
                'success' => true,
                'message' => 'Order created successfully',
                'data' => $this->serializeOrder($order),
            ], 201);
        } catch (\Throwable $e) {
            error_log('Order creation error: '.$e->getMessage().' - '.$e->getTraceAsString());

            return new JsonResponse([
                'success' => false,
                'message' => 'Order creation failed: '.$e->getMessage(),
            ], 500);
        }
    }

    #[Route('/orders/{id}', name: 'api_update_order', requirements: ['id' => '\d+'], methods: ['PUT', 'PATCH'])]
    public function updateOrder(int $id, Request $request, OrderRepository $orderRepository, EntityManagerInterface $entityManager): JsonResponse
    {
        $user = $this->getUser();

        if (!$user) {
            return new JsonResponse([
                'success' => false,
                'message' => 'Authentication required',
            ], 401);
        }

        $order = $orderRepository->find($id);

        if (!$order) {
            return new JsonResponse([
                'success' => false,
                'message' => 'Order not found',
            ], 404);
        }

        if (!$this->canAccessOrder($user, $order)) {
            return new JsonResponse([
                'success' => false,
                'message' => 'Access denied',
            ], 403);
        }

        $previousStatus = $order->getStatus();
        $data = json_decode($request->getContent(), true);

        if (!$data) {
            return new JsonResponse([
                'success' => false,
                'message' => 'Invalid JSON body',
            ], 400);
        }

        /** @var User $user */
        $isStaffOrAdmin = in_array('ROLE_STAFF', $user->getRoles()) || in_array('ROLE_ADMIN', $user->getRoles());

        if ($isStaffOrAdmin) {
            if (isset($data['customer_name'])) {
                $order->setCustomerName($data['customer_name']);
            }
            if (isset($data['product_name'])) {
                $order->setProductName($data['product_name']);
            }
            if (isset($data['material'])) {
                $order->setMaterial($data['material']);
            }
            if (isset($data['color'])) {
                $order->setColor($data['color']);
            }
            if (isset($data['quantity'])) {
                $order->setQuantity((int) $data['quantity']);
            }
            if (isset($data['price'])) {
                $order->setPrice((float) $data['price']);
            }
            if (isset($data['delivery_date'])) {
                $order->setDeliveryDate(new \DateTimeImmutable($data['delivery_date']));
            }
            if (isset($data['status']) && Order::isValidStatus((string) $data['status'])) {
                $order->setStatus((string) $data['status']);
            }
        } else {
            if (isset($data['customer_name'])) {
                $order->setCustomerName($data['customer_name']);
            }
            if (isset($data['color'])) {
                $order->setColor($data['color']);
            }
        }

        if ($order->getQuantity() !== null && $order->getPrice() !== null) {
            $order->setTotalAmount($order->getPrice() * $order->getQuantity());
        }

        $entityManager->flush();

        if ($isStaffOrAdmin && isset($data['status']) && \is_string($previousStatus) && $previousStatus !== $order->getStatus()) {
            $this->notifyCustomerOrderStatus($order, $previousStatus);
        }

        return new JsonResponse([
            'success' => true,
            'message' => 'Order updated successfully',
            'data' => $this->serializeOrder($order),
        ]);
    }

    #[Route('/orders/{id}/status', name: 'api_order_status', requirements: ['id' => '\d+'], methods: ['PATCH', 'POST'])]
    public function updateOrderStatus(int $id, Request $request, OrderRepository $orderRepository, EntityManagerInterface $entityManager): JsonResponse
    {
        $user = $this->getUser();

        if (!$user instanceof User) {
            return new JsonResponse(['success' => false, 'message' => 'Authentication required'], 401);
        }

        $roles = $user->getRoles();
        if (!\in_array('ROLE_STAFF', $roles, true) && !\in_array('ROLE_ADMIN', $roles, true)) {
            return new JsonResponse(['success' => false, 'message' => 'Access denied'], 403);
        }

        $order = $orderRepository->find($id);
        if (!$order) {
            return new JsonResponse(['success' => false, 'message' => 'Order not found'], 404);
        }

        $data = json_decode($request->getContent(), true);
        $newStatus = \is_array($data) && isset($data['status']) ? (string) $data['status'] : $request->query->getString('status');

        if (!Order::isValidStatus($newStatus)) {
            return new JsonResponse(['success' => false, 'message' => 'Invalid status. Use pending, complete, or cancelled.'], 400);
        }

        $previousStatus = $order->getStatus();
        if ($previousStatus === $newStatus) {
            return new JsonResponse([
                'success' => true,
                'message' => 'Status unchanged',
                'data' => $this->serializeOrder($order),
            ]);
        }

        $order->setStatus($newStatus);
        $entityManager->flush();

        $this->notifyCustomerOrderStatus($order, $previousStatus);

        return new JsonResponse([
            'success' => true,
            'message' => 'Order status updated',
            'data' => $this->serializeOrder($order),
        ]);
    }

    #[Route('/orders/{id}', name: 'api_delete_order', requirements: ['id' => '\d+'], methods: ['DELETE'])]
    public function deleteOrder(int $id, OrderRepository $orderRepository, EntityManagerInterface $entityManager): JsonResponse
    {
        $user = $this->getUser();

        if (!$user) {
            return new JsonResponse([
                'success' => false,
                'message' => 'Authentication required',
            ], 401);
        }

        $order = $orderRepository->find($id);

        if (!$order) {
            return new JsonResponse([
                'success' => false,
                'message' => 'Order not found',
            ], 404);
        }

        if (!$this->canAccessOrder($user, $order)) {
            return new JsonResponse([
                'success' => false,
                'message' => 'Access denied',
            ], 403);
        }

        $entityManager->remove($order);
        $entityManager->flush();

        return new JsonResponse([
            'success' => true,
            'message' => 'Order deleted successfully',
        ]);
    }

    private function buildOrderFromProduct(Product $product, array $data, User $user): Order
    {
        $order = new Order();
        $order->setCustomerName($data['customer_name']);
        $order->setProductName($product->getName());
        $order->setMaterial($data['material'] ?? $product->getMaterial());
        $order->setColor($data['color'] ?? $product->getColor());
        $order->setQuantity((int) $data['quantity']);
        $order->setPrice($product->getPrice());
        $order->setTotalAmount($product->getPrice() * (int) $data['quantity']);
        $order->setDate(new \DateTime());
        $order->setDeliveryDate(new \DateTimeImmutable('+7 days'));
        $order->setCreatedBy($user);
        $order->setStatus(Order::STATUS_PENDING);

        return $order;
    }

    private function notifyCustomerOrderStatus(Order $order, string $previousStatus): void
    {
        $customer = $order->getCreatedBy();
        if (!$customer instanceof User) {
            return;
        }

        $freshCustomer = $this->userRepository->find($customer->getId());
        if ($freshCustomer === null) {
            return;
        }

        $order->setCreatedBy($freshCustomer);
        $this->pushNotificationService->notifyOrderStatusChanged($order, $previousStatus);
    }

    private function canAccessOrder(User $user, Order $order): bool
    {
        $roles = $user->getRoles();
        if (in_array('ROLE_STAFF', $roles) || in_array('ROLE_ADMIN', $roles)) {
            return true;
        }

        return $order->getCreatedBy() === $user;
    }

    private function serializeOrder(Order $order): array
    {
        return [
            'id' => $order->getId(),
            'customer_name' => $order->getCustomerName(),
            'product_name' => $order->getProductName(),
            'material' => $order->getMaterial(),
            'color' => $order->getColor(),
            'quantity' => $order->getQuantity(),
            'price' => $order->getPrice(),
            'total_amount' => $order->getTotalAmount(),
            'date' => $order->getDate()?->format('Y-m-d H:i:s'),
            'delivery_date' => $order->getDeliveryDate()?->format('Y-m-d'),
            'status' => $order->getStatus(),
            'created_by' => $order->getCreatedBy()?->getEmail(),
        ];
    }
}
