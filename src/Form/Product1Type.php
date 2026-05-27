<?php

namespace App\Form;

use App\Entity\Product;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\NumberType;

class Product1Type extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('name', TextType::class, [
                'attr' => ['class' => 'form-control', 'placeholder' => 'Enter product name'],
                'required' => true,
            ])
            ->add('description', TextareaType::class, [
                'attr' => ['class' => 'form-control', 'placeholder' => 'Enter product description', 'rows' => 4],
                'required' => true,
            ])
            ->add('price', NumberType::class, [
                'attr' => ['class' => 'form-control', 'placeholder' => 'Enter price', 'step' => '0.01'],
                'required' => true,
            ])
            ->add('image', TextType::class, [
                'attr' => ['class' => 'form-control', 'placeholder' => 'Enter image filename (e.g., product.jpg)'],
                'required' => true,
            ])
            ->add('material', ChoiceType::class, [
                'choices' => [
                    'Select Material' => '',
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
                'attr' => ['class' => 'form-control'],
                'required' => true,
            ])
            ->add('color', TextType::class, [
                'attr' => ['class' => 'form-control', 'placeholder' => 'Enter product color'],
                'required' => true,
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Product::class,
            'csrf_protection' => true,
            'csrf_field_name' => '_token',
            'csrf_token_id' => 'product_item',
        ]);
    }
}
