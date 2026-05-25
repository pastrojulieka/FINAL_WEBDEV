<?php

namespace App\Form;

use App\Entity\Product;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;

class Product1Type extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('name')
            ->add('description')
            ->add('price')
            ->add('image')
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
            ->add('color');
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Product::class,
        ]);
    }
}
