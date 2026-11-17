<?php

namespace App\Controller;

use App\Entity\Review;
use App\Entity\User;
use App\Form\ReviewSubmitFormType;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * "Rate us": any logged-in user can leave a review.
 * Guests are sent to the login page first (see access_control in security.yaml).
 */
class ReviewController extends AbstractController
{
    #[Route('/review', name: 'app_review_new', methods: ['GET', 'POST'])]
    #[IsGranted('ROLE_USER')]
    public function new(Request $request, EntityManagerInterface $em): Response
    {
        /** @var User $user */
        $user = $this->getUser();

        $form = $this->createForm(ReviewSubmitFormType::class);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $data = $form->getData();
            $comment = trim((string) ($data['comment'] ?? ''));

            $review = new Review();
            $review->setUser($user);
            $review->setRating((int) $data['rating']);
            $review->setComment('' === $comment ? null : $comment);

            $em->persist($review);
            $em->flush();

            $this->addFlash('success', 'Thank you! Your review was posted.');

            return $this->redirectToRoute('app_review_new');
        }

        return $this->render('review/new.html.twig', [
            'form' => $form,
        ]);
    }
}