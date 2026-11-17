<?php

namespace App\Form;

use App\Entity\Booking;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\TimeType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;

/** Admin: edit any booking. */
class BookingFormType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('planName', TextType::class, [
                'label' => 'Plan',
                'constraints' => [
                    new NotBlank(message: 'Please enter the plan name.'),
                    new Length(max: 255),
                ],
            ])
            ->add('bookingDate', DateType::class, [
                'label' => 'Date',
                'widget' => 'single_text',
                'input' => 'datetime_immutable',
                'constraints' => [new NotBlank(message: 'Please pick a date.')],
            ])
            ->add('bookingTime', TimeType::class, [
                'label' => 'Start time',
                'widget' => 'single_text',
                'input' => 'datetime_immutable',
                'constraints' => [new NotBlank(message: 'Please pick a start time.')],
            ])
            ->add('bookingTimeEnd', TimeType::class, [
                'label' => 'End time',
                'widget' => 'single_text',
                'input' => 'datetime_immutable',
                'constraints' => [new NotBlank(message: 'Please pick an end time.')],
            ])
            ->add('status', ChoiceType::class, [
                'label' => 'Status',
                'choices' => ['Pending' => 'pending', 'Confirmed' => 'confirmed', 'Cancelled' => 'cancelled'],
            ])
            ->add('paymentMethod', ChoiceType::class, [
                'label' => 'Payment method',
                'required' => false,
                'placeholder' => 'None',
                'choices' => ['GCash' => 'gcash', 'Maya' => 'maya'],
            ])
            ->add('paymentStatus', ChoiceType::class, [
                'label' => 'Payment status',
                'choices' => ['Unpaid' => 'unpaid', 'Paid' => 'paid'],
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(['data_class' => Booking::class]);
    }
}