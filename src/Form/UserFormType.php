<?php

namespace App\Form;

use App\Entity\User;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;

class UserFormType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $isEdit = $options['is_edit'] ?? false;
        
        $builder
            ->add('email', TextType::class, [
                'label' => 'Email',
                'attr' => ['class' => 'form-control', 'placeholder' => 'Enter email address'],
            ])
            ->add('role', ChoiceType::class, [
                'label' => 'Role',
                'mapped' => false,
                'choices' => [
                    'User' => 'ROLE_USER',
                    'Staff' => 'ROLE_STAFF',
                    'Admin' => 'ROLE_ADMIN',
                ],
                'multiple' => false,
                'expanded' => false,
                'attr' => ['class' => 'form-control'],
                'required' => true,
            ])
            ->add('plainPassword', PasswordType::class, [
                'label' => $isEdit ? 'New Password (leave blank to keep current)' : 'Password',
                'mapped' => false,
                'required' => !$isEdit,
                'attr' => [
                    'class' => 'form-control',
                    'autocomplete' => 'new-password',
                    'placeholder' => $isEdit ? 'Enter new password or leave blank' : 'Enter password'
                ],
                'constraints' => $isEdit ? [] : [
                    new NotBlank([
                        'message' => 'Please enter a password',
                    ]),
                    new Length([
                        'min' => 6,
                        'minMessage' => 'Your password should be at least {{ limit }} characters',
                        'max' => 4096,
                    ]),
                ],
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => User::class,
            'is_edit' => false,
        ]);
    }
}

