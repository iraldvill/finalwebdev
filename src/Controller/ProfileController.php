<?php

namespace App\Controller;

use App\Entity\User;
use App\Form\ChangePasswordFormType;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\FormError;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * My Profile: any logged-in user (customer, staff or admin) can see their own
 * account details and change their own password.
 */
class ProfileController extends AbstractController
{
    #[Route('/profile', name: 'app_profile')]
    #[IsGranted('ROLE_USER')]
    public function index(
        Request $request,
        EntityManagerInterface $em,
        UserPasswordHasherInterface $hasher,
    ): Response {
        /** @var User $user */
        $user = $this->getUser();

        $form = $this->createForm(ChangePasswordFormType::class);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $newPassword = $form->get('newPassword')->getData();

            if ($hasher->isPasswordValid($user, $newPassword)) {
                $form->get('newPassword')->get('first')->addError(
                    new FormError('Your new password must be different from the current one.')
                );
            } else {
                $user->setPassword($hasher->hashPassword($user, $newPassword));
                $em->flush();

                $this->addFlash('success', 'Your password was changed.');

                return $this->redirectToRoute('app_profile');
            }
        }

        $roles = $user->getRoles();
        $role = match (true) {
            in_array('ROLE_ADMIN', $roles, true) => 'admin',
            in_array('ROLE_STAFF', $roles, true) => 'staff',
            default => 'customer',
        };

        return $this->render('profile/index.html.twig', [
            'user' => $user,
            'role' => $role,
            'form' => $form,
        ]);
    }
}