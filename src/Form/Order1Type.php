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
            ->add('customer_name', TextType::class, [
                'label' => 'Customer Name',
            ])
            ->add('product_name', TextType::class, [
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
                'attr' => ['min' => 1],
            ])
            ->add('price', NumberType::class, [
                'label' => 'Unit Price',
                'html5' => true,
                'scale' => 2,
                'attr' => ['step' => '0.01', 'min' => 0],
            ])
            ->add('total_amount', NumberType::class, [
                'label' => 'Total Amount',
                'html5' => true,
                'scale' => 2,
                'attr' => ['step' => '0.01', 'min' => 0],
            ])
            ->add('date', DateTimeType::class, [
                'label' => 'Order Date',
                'widget' => 'single_text',
                'input' => 'datetime',
            ])
            ->add('delivery_date', DateType::class, [
                'label' => 'Delivery Date',
                'widget' => 'single_text',
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
