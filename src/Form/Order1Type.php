<?php

namespace App\Form;

use App\Entity\Order;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\DateTimeType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\NumberType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class Order1Type extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('customerName', TextType::class, [
                'label' => 'Customer Name',
            ])
            ->add('productName', TextType::class, [
                'label' => 'Product Name',
            ])
            ->add('material', TextType::class, [
                'label' => 'Material',
                'attr' => ['placeholder' => 'e.g. plastic, wood, metal'],
            ])
            ->add('color', TextType::class, [
                'label' => 'Color',
            ])
            ->add('quantity', NumberType::class, [
                'label' => 'Quantity',
                'html5' => true,
                'input' => 'number',
                'attr' => ['min' => 1, 'step' => 1],
            ])
            ->add('price', NumberType::class, [
                'label' => 'Unit Price',
                'html5' => true,
                'scale' => 2,
                'input' => 'number',
                'attr' => ['step' => '0.01', 'min' => 0],
            ])
            ->add('totalAmount', NumberType::class, [
                'label' => 'Total Amount',
                'html5' => true,
                'scale' => 2,
                'input' => 'number',
                'attr' => ['step' => '0.01', 'min' => 0],
            ])
            ->add('date', DateTimeType::class, [
                'label' => 'Order Date',
                'widget' => 'single_text',
                'html5' => true,
                'input' => 'datetime',
                'with_seconds' => false,
            ])
            ->add('deliveryDate', DateType::class, [
                'label' => 'Delivery Date',
                'widget' => 'single_text',
                'html5' => true,
                'input' => 'datetime_immutable',
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Order::class,
        ]);
    }
}
