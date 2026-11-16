<?php

namespace App\Form;

use App\Entity\User;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\Email;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;

/**
 * The admin's form for adding and editing an account.
 */
class UserFormType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $isNew = $options['is_new'];

        $passwordConstraints = [
            new Length(
                min: 8,
                minMessage: 'The password should be at least {{ limit }} characters.',
                max: 4096,
            ),
        ];
        if ($isNew) {
            array_unshift($passwordConstraints, new NotBlank(message: 'Please enter a password.'));
        }

        $builder
            ->add('fullName', TextType::class, [
                'label' => 'Full name',
                'constraints' => [
                    new NotBlank(message: 'Please enter a name.'),
                    new Length(max: 255),
                ],
            ])
            ->add('email', EmailType::class, [
                'label' => 'Email',
                'constraints' => [
                    new NotBlank(message: 'Please enter an email.'),
                    new Email(message: 'That does not look like a valid email.'),
                ],
            ])
            ->add('role', ChoiceType::class, [
                'label' => 'Role',
                'mapped' => false, // handled in the controller
                'choices' => [
                    'Customer' => 'customer',
                    'Staff' => 'staff',
                    'Admin' => 'admin',
                ],
                'data' => $options['current_role'],
                'constraints' => [new NotBlank(message: 'Please choose a role.')],
            ])
            ->add('plainPassword', PasswordType::class, [
                'label' => $isNew ? 'Password' : 'New password (leave blank to keep the current one)',
                'mapped' => false, // never stored as plain text; hashed in the controller
                'required' => $isNew,
                'constraints' => $passwordConstraints,
                'attr' => ['autocomplete' => 'new-password'],
            ])
            ->add('active', CheckboxType::class, [
                'label' => 'Account is active (untick to disable it)',
                'required' => false,
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => User::class,
            'is_new' => false,
            'current_role' => 'customer',
        ]);
        $resolver->setAllowedTypes('is_new', 'bool');
        $resolver->setAllowedTypes('current_role', 'string');
    }
}