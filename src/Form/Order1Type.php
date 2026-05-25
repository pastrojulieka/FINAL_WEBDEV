<?php

namespace App\Form;

use App\Entity\Order;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;

class Order1Type extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('customer_name')
            ->add('product_name')
            ->add('material', ChoiceType::class, [
                'choices' => [
                    'Plastic' => 'plastic',
                    'Metal' => 'metal',
                    'Wood' => 'wood',
                    'Glass' => 'glass',
                    'Fabric' => 'fabric',
                    'Leather' => 'leather',
                    'Bamboo' => 'bamboo',
                    'Rattan' => 'rattan',
                    'Cotton' => 'cotton',
                ],
                'placeholder' => 'Choose a material', // optional
            ])
            ->add('color')
            ->add('quantity')
            ->add('price')
            ->add('total_amount')
            ->add('date')
            ->add('delivery_date', null, [
                'widget' => 'single_text',
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
