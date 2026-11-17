<?php

namespace App\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;

/**
 * "Rate us": a star rating plus optional feedback.
 * Not tied to an entity — the controller builds the Review from the answers.
 */
class ReviewSubmitFormType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('rating', ChoiceType::class, [
                'label' => 'Your rating',
                // 5 first: the page shows them reversed so the stars read 1 → 5, left to right.
                'choices' => [
                    '5 stars' => 5,
                    '4 stars' => 4,
                    '3 stars' => 3,
                    '2 stars' => 2,
                    '1 star' => 1,
                ],
                'expanded' => true,
                'multiple' => false,
                'choice_label' => fn () => '★',
                'choice_attr' => fn (int $stars) => ['title' => $stars.($stars > 1 ? ' stars' : ' star')],
                'constraints' => [new NotBlank(message: 'Please pick a star rating.')],
            ])
            ->add('comment', TextareaType::class, [
                'label' => 'Feedback (optional)',
                'required' => false,
                'attr' => ['rows' => 5, 'placeholder' => 'Tell us about your visit…'],
                'constraints' => [new Length(max: 1000, maxMessage: 'Feedback can be at most {{ limit }} characters.')],
            ]);
    }
}