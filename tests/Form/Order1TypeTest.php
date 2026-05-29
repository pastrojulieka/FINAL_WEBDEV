<?php

namespace App\Tests\Form;

use App\Entity\Order;
use App\Form\Order1Type;
use Symfony\Component\Form\Test\TypeTestCase;

class Order1TypeTest extends TypeTestCase
{
    public function testSubmitValidData(): void
    {
        $order = new Order();
        $order->setCustomerName('John');
        $order->setProductName('Chair');
        $order->setMaterial('wood');
        $order->setColor('brown');
        $order->setQuantity(2);
        $order->setPrice(100.0);
        $order->setTotalAmount(200.0);
        $order->setDate(new \DateTime('2025-01-15 10:00:00'));
        $order->setDeliveryDate(new \DateTimeImmutable('2025-01-22'));

        $form = $this->factory->create(Order1Type::class, $order);
        $form->submit([
            'customerName' => 'Jane Updated',
            'productName' => 'Desk',
            'material' => 'metal',
            'color' => 'black',
            'quantity' => 3,
            'price' => 150,
            'totalAmount' => 450,
            'date' => '2025-01-15T10:00',
            'deliveryDate' => '2025-01-22',
        ]);

        $this->assertTrue($form->isSynchronized());
        $this->assertTrue($form->isValid());
        $this->assertSame('Jane Updated', $order->getCustomerName());
        $this->assertSame('Desk', $order->getProductName());
        $this->assertSame(450.0, $order->getTotalAmount());
    }
}
