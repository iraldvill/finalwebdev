<?php

namespace App\Form;

use App\Entity\User;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\Form\Extension\Core\Type\RepeatedType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\Email;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;

class RegistrationFormType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('fullName', TextType::class, [
                'label' => 'Full name',
                'constraints' => [
                    new NotBlank(message: 'Please enter your name.'),
                    new Length(max: 255),
                ],
                'attr' => ['autocomplete' => 'name'],
            ])
            ->add('email', EmailType::class, [
                'label' => 'Email',
                'constraints' => [
                    new NotBlank(message: 'Please enter your email.'),
                    new Email(message: 'That does not look like a valid email.'),
                ],
                'attr' => ['autocomplete' => 'email'],
            ])
            ->add('role', ChoiceType::class, [
                'label' => 'Account type',
                'mapped' => false, // handled in the controller
                'choices' => [
                    'Customer' => 'customer',
                    'Staff' => 'staff',
                    'Admin' => 'admin',
                ],
                'data' => 'customer',
                'constraints' => [new NotBlank(message: 'Please choose an account type.')],
            ])
            ->add('registrationCode', TextType::class, [
                'label' => 'Registration code',
                'mapped' => false,
                'required' => false,
                'attr' => ['autocomplete' => 'off', 'placeholder' => 'Staff and admin only'],
            ])
            ->add('plainPassword', RepeatedType::class, [
                'type' => PasswordType::class,
                'mapped' => false, // never stored; we hash it in the controller
                'invalid_message' => 'The two passwords must match.',
                'first_options' => [
                    'label' => 'Password',
                    'attr' => ['autocomplete' => 'new-password'],
                ],
                'second_options' => [
                    'label' => 'Confirm password',
                    'attr' => ['autocomplete' => 'new-password'],
                ],
                'constraints' => [
                    new NotBlank(message: 'Please enter a password.'),
                    new Length(
                        min: 8,
                        minMessage: 'Your password should be at least {{ limit }} characters.',
                        max: 4096,
                    ),
                ],
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(['data_class' => User::class]);
    }
}