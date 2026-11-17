<?php

namespace App\Form;

use App\Entity\User;
use App\Repository\UserRepository;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\TimeType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Validator\Constraints\Count;
use Symfony\Component\Validator\Constraints\NotBlank;

/**
 * Staff: create a booking for a customer.
 * Not tied to an entity — the controller builds the Booking from the answers.
 */
class StaffBookingFormType extends AbstractType
{
    public function __construct(private readonly UserRepository $userRepository)
    {
    }

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        // Only customer accounts that are enabled (no admins or staff).
        $customers = array_values(array_filter(
            $this->userRepository->findBy([], ['fullName' => 'ASC']),
            fn (User $u) => $u->isActive()
                && !in_array('ROLE_ADMIN', $u->getRoles(), true)
                && !in_array('ROLE_STAFF', $u->getRoles(), true),
        ));

        $stations = [];
        foreach (range(1, 30) as $n) {
            $stations['PC '.$n] = $n;
        }

        $builder
            ->add('customer', EntityType::class, [
                'class' => User::class,
                'choices' => $customers,
                'choice_label' => fn (User $u) => $u->getFullName().' ('.$u->getEmail().')',
                'placeholder' => 'Choose a customer…',
                'label' => 'Customer',
                'constraints' => [new NotBlank(message: 'Please choose a customer.')],
            ])
            ->add('plan', ChoiceType::class, [
                'label' => 'Plan',
                'choices' => [
                    'Hourly Gaming — ₱50 / hour (1 PC)' => 'Hourly Gaming',
                    'Extended Gaming — ₱200 / 5 hours (1 PC)' => 'Extended Gaming',
                    'Gaming Package — ₱180 / hour (3 PCs)' => 'Gaming Package',
                ],
                'placeholder' => 'Choose a plan…',
                // How many PCs each plan needs — the page uses this to limit the PC picker.
                'choice_attr' => fn (string $plan) => array_filter([
                    'data-pcs' => 'Gaming Package' === $plan ? 3 : 1,
                    'data-hours' => 'Extended Gaming' === $plan ? 5 : null, // fixed length
                ]),
                'constraints' => [new NotBlank(message: 'Please choose a plan.')],
            ])
            ->add('bookingDate', DateType::class, [
                'label' => 'Date',
                'widget' => 'single_text',
                'input' => 'datetime_immutable',
                // The calendar greys out days before today (Philippine time).
                'attr' => ['min' => (new \DateTimeImmutable('today', new \DateTimeZone('Asia/Manila')))->format('Y-m-d')],
                'constraints' => [new NotBlank(message: 'Please pick a date.')],
            ])
            ->add('startTime', TimeType::class, [
                'label' => 'Start time',
                'widget' => 'single_text',
                'input' => 'datetime_immutable',
                'constraints' => [new NotBlank(message: 'Please pick a start time.')],
            ])
            ->add('endTime', TimeType::class, [
                'label' => 'End time',
                'widget' => 'single_text',
                'input' => 'datetime_immutable',
                'required' => false, // Extended Gaming sets its own end time (start + 5 hours)
            ])
            ->add('stations', ChoiceType::class, [
                'label' => 'PC stations',
                'choices' => $stations,
                'choice_attr' => fn (int $n) => ['data-pc' => $n],
                'multiple' => true,
                'expanded' => true,
                'constraints' => [new Count(min: 1, minMessage: 'Please pick at least one PC.')],
            ])
            ->add('paymentMethod', ChoiceType::class, [
                'label' => 'Payment method',
                'required' => false,
                'placeholder' => 'Pay at the counter',
                'choices' => ['GCash' => 'gcash', 'Maya' => 'maya'],
            ]);
    }
}