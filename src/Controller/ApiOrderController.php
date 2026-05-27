<?php

namespace App\Controller;

use App\Entity\Order;
use App\Entity\Product;
use App\Entity\User;
use App\Repository\OrderRepository;
use App\Repository\ProductRepository;
use App\Repository\StockRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api')]
class ApiOrderController extends AbstractController
{
    #[Route('/orders', name: 'api_orders', methods: ['GET'])]
    public function getOrders(Request $request, OrderRepository $orderRepository): JsonResponse
    {
        $user = $this->getUser();

        if (!$user) {
            return new JsonResponse([
                'success' => false,
                'message' => 'Authentication required'
            ], 401);
        }

        /** @var User $user */
        $roles = $user->getRoles();
        $isStaffOrAdmin = in_array('ROLE_STAFF', $roles) || in_array('ROLE_ADMIN', $roles);

        if ($isStaffOrAdmin) {
            // Staff/Admin — return all orders
            $orders = $orderRepository->findBy([], ['date' => 'DESC']);
        } else {
            // Customer — filter by customer_name query param
            $customerName = $request->query->get('customer_name');

            if (!$customerName) {
                return new JsonResponse([
                    'success' => false,
                    'message' => 'customer_name query parameter is required'
                ], 400);
            }

            $orders = $orderRepository->findBy(['customer_name' => $customerName], ['date' => 'DESC']);
        }

        $orderData = [];

        foreach ($orders as $order) {
            $orderData[] = [
                'id' => $order->getId(),
                'customer_name' => $order->getCustomerName(),
                'product_name' => $order->getProductName(),
                'material' => $order->getMaterial(),
                'color' => $order->getColor(),
                'quantity' => $order->getQuantity(),
                'price' => $order->getPrice(),
                'total_amount' => $order->getTotalAmount(),
                'date' => $order->getDate()->format('Y-m-d H:i:s'),
                'delivery_date' => $order->getDeliveryDate() ? $order->getDeliveryDate()->format('Y-m-d') : null,
                'created_by' => $order->getCreatedBy() ? $order->getCreatedBy()->getEmail() : null
            ];
        }

        return new JsonResponse([
            'success' => true,
            'data' => $orderData
        ]);
    }

    #[Route('/orders/{id}', name: 'api_order_show', methods: ['GET'])]
    public function getOrder(int $id, OrderRepository $orderRepository): JsonResponse
    {
        $user = $this->getUser();

        if (!$user) {
            return new JsonResponse([
                'success' => false,
                'message' => 'Authentication required'
            ], 401);
        }

        $order = $orderRepository->find($id);

        if (!$order) {
            return new JsonResponse([
                'success' => false,
                'message' => 'Order not found'
            ], 404);
        }

        /** @var User $user */
        // Check if user has permission to view this order
        $roles = $user->getRoles();
        if (!in_array('ROLE_STAFF', $roles) && !in_array('ROLE_ADMIN', $roles)) {
            // Customer can only view their own orders
            if ($order->getCustomerName() !== $user->getUserIdentifier()) {
                return new JsonResponse([
                    'success' => false,
                    'message' => 'Access denied'
                ], 403);
            }
        }

        return new JsonResponse([
            'success' => true,
            'data' => [
                'id' => $order->getId(),
                'customer_name' => $order->getCustomerName(),
                'product_name' => $order->getProductName(),
                'material' => $order->getMaterial(),
                'color' => $order->getColor(),
                'quantity' => $order->getQuantity(),
                'price' => $order->getPrice(),
                'total_amount' => $order->getTotalAmount(),
                'date' => $order->getDate()->format('Y-m-d H:i:s'),
                'delivery_date' => $order->getDeliveryDate() ? $order->getDeliveryDate()->format('Y-m-d') : null,
                'created_by' => $order->getCreatedBy() ? $order->getCreatedBy()->getEmail() : null
            ]
        ]);
    }

    #[Route('/orders', name: 'api_create_order', methods: ['POST'])]
    public function createOrder(Request $request, EntityManagerInterface $entityManager, ProductRepository $productRepository, StockRepository $stockRepository): JsonResponse
    {
        try {
            $user = $this->getUser();

            if (!$user) {
                return new JsonResponse([
                    'success' => false,
                    'message' => 'Authentication required'
                ], 401);
            }

            $data = json_decode($request->getContent(), true);

            if (!$data || !isset($data['product_id'], $data['quantity'], $data['customer_name'])) {
                return new JsonResponse([
                    'success' => false,
                    'message' => 'Product ID, quantity, and customer_name are required'
                ], 400);
            }

            // Validate quantity
            if ((int)$data['quantity'] <= 0) {
                return new JsonResponse([
                    'success' => false,
                    'message' => 'Quantity must be greater than 0'
                ], 400);
            }

            $product = $productRepository->find($data['product_id']);

            if (!$product) {
                return new JsonResponse([
                    'success' => false,
                    'message' => 'Product not found'
                ], 404);
            }

            // Check if enough stock is available (sum across all Stock entries)
            $stocks = $stockRepository->findBy(['product' => $product]);
            $totalStock = array_sum(array_map(fn($s) => $s->getQuantity(), $stocks));
            // Fallback to product's own quantity if no stock entries exist
            if (count($stocks) === 0) {
                $totalStock = $product->getQuantity();
            }

            if ($totalStock < $data['quantity']) {
                return new JsonResponse([
                    'success' => false,
                    'message' => 'Insufficient stock available. Available: ' . $totalStock . ', Requested: ' . $data['quantity']
                ], 400);
            }

            $order = new Order();
            $order->setCustomerName($data['customer_name']);
            $order->setProductName($product->getName());
            $order->setMaterial($data['material'] ?? $product->getMaterial());
            $order->setColor($data['color'] ?? $product->getColor());
            $order->setQuantity((int)$data['quantity']);
            $order->setPrice($product->getPrice());
            $order->setTotalAmount($product->getPrice() * (int)$data['quantity']);
            $order->setDate(new \DateTime());

            // Set delivery date (default to 7 days from now)
            $deliveryDate = new \DateTimeImmutable();
            $deliveryDate = $deliveryDate->modify('+7 days');
            $order->setDeliveryDate($deliveryDate);

            $order->setCreatedBy($user);

            // Update product quantity
            $product->setQuantity($product->getQuantity() - (int)$data['quantity']);

            $entityManager->persist($order);
            $entityManager->flush();

            error_log('Order created successfully: ID=' . $order->getId() . ', Customer=' . $data['customer_name'] . ', Product=' . $product->getName());

            return new JsonResponse([
                'success' => true,
                'message' => 'Order created successfully',
                'data' => [
                    'id' => $order->getId(),
                    'customer_name' => $order->getCustomerName(),
                    'product_name' => $order->getProductName(),
                    'material' => $order->getMaterial(),
                    'color' => $order->getColor(),
                    'quantity' => $order->getQuantity(),
                    'price' => $order->getPrice(),
                    'total_amount' => $order->getTotalAmount(),
                    'date' => $order->getDate()->format('Y-m-d H:i:s'),
                    'delivery_date' => $order->getDeliveryDate()->format('Y-m-d')
                ]
            ], 201);
        } catch (\Throwable $e) {
            error_log('Order creation error: ' . $e->getMessage() . ' - ' . $e->getTraceAsString());
            return new JsonResponse([
                'success' => false,
                'message' => 'Order creation failed: ' . $e->getMessage()
            ], 500);
        }
    }
}
