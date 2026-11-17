<?php

namespace App\Controller;

use App\Entity\Review;
use App\Form\ReviewFormType;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Admin only: edit and delete ANY review.
 * The list of reviews is the "Reviews" tab on the dashboard.
 */
#[Route('/admin/reviews')]
#[IsGranted('ROLE_ADMIN')]
class AdminReviewController extends AbstractController
{
    #[Route('/{id}/edit', name: 'app_admin_review_edit', requirements: ['id' => '\d+'], methods: ['GET', 'POST'])]
    public function edit(Review $review, Request $request, EntityManagerInterface $em): Response
    {
        $form = $this->createForm(ReviewFormType::class, $review);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $em->flush();
            $this->addFlash('success', 'Review was updated.');

            return $this->backToReviews();
        }

        return $this->render('admin/review_form.html.twig', [
            'form' => $form,
            'review' => $review,
        ]);
    }

    #[Route('/{id}/delete', name: 'app_admin_review_delete', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function delete(Review $review, Request $request, EntityManagerInterface $em): Response
    {
        if (!$this->isCsrfTokenValid('delete-review-'.$review->getId(), $request->request->getString('_token'))) {
            $this->addFlash('danger', 'Invalid request. Please try again.');

            return $this->backToReviews();
        }

        $em->remove($review);
        $em->flush();

        $this->addFlash('success', 'Review was deleted.');

        return $this->backToReviews();
    }

    private function backToReviews(): Response
    {
        return $this->redirect($this->generateUrl('app_dashboard').'#reviews');
    }
}