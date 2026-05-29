<?php

namespace App\Service;

use App\Entity\Order;
use App\Repository\OrderRepository;

class OrderLiveService
{
    public function __construct(
        private OrderRepository $orderRepository,
    ) {
    }

    /**
     * @param Order[] $orders
     */
    public function computeFingerprint(array $orders): string
    {
        if ($orders === []) {
            return 'empty';
        }

        $parts = [];
        foreach ($orders as $order) {
            $parts[] = sprintf(
                '%d:%s:%s:%s',
                $order->getId(),
                $order->getTotalAmount(),
                $order->getQuantity(),
                $order->getDate()?->format('Y-m-d H:i:s') ?? ''
            );
        }

        return hash('sha256', implode('|', $parts));
    }

    /**
     * @return Order[]
     */
    public function getAllOrdersSorted(): array
    {
        return $this->orderRepository->findBy([], ['date' => 'DESC']);
    }

    /**
     * @param Order[] $orders
     */
    public function serializeOrders(array $orders): array
    {
        return array_map(fn (Order $order) => $this->serializeOrder($order), $orders);
    }

    public function serializeOrder(Order $order): array
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
        ];
    }
}
