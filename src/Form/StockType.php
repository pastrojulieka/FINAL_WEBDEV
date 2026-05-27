<?php

namespace App\Form;

use App\Entity\Order;
use App\Entity\Product;
use App\Entity\Stock;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class StockType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('product', EntityType::class, [
                'class' => Product::class,
                'choice_label' => 'name',
                'placeholder' => 'Select product',
                'required' => true,
                'attr' => ['class' => 'form-control']
            ])
            ->add('quantity', IntegerType::class, [
                'label' => 'Quantity',
                'attr' => ['min' => 0, 'class' => 'form-control'],
                'required' => true,
            ])
            ->add('status', ChoiceType::class, [
                'label' => 'Status',
                'choices' => [
                    'In Stock' => 'In Stock',
                    'Low Stock' => 'Low Stock',
                    'Out of Stock' => 'Out of Stock',
                ],
                'placeholder' => 'Select status',
                'attr' => ['class' => 'form-control'],
                'required' => true,
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Stock::class,
            'csrf_protection' => true,
            'csrf_field_name' => '_token',
            'csrf_token_id' => 'stock_item',
        ]);
    }
}
