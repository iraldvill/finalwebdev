<?php

namespace App\Controller;

use App\Entity\User;
use App\Form\RegistrationFormType;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;

class RegistrationController extends AbstractController
{
    #[Route('/register', name: 'app_register')]
    public function register(
        Request $request,
        UserPasswordHasherInterface $passwordHasher,
        EntityManagerInterface $entityManager,
    ): Response {
        // Already logged in? Nothing to register.
        if ($this->getUser()) {
            return $this->redirectToRoute('app_home');
        }

        $user = new User();
        $form = $this->createForm(RegistrationFormType::class, $user);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            // Hash the password. The plain text is never saved.
            $plainPassword = $form->get('plainPassword')->getData();
            $user->setPassword($passwordHasher->hashPassword($user, $plainPassword));

            // NOTE: anyone can pick any account type here, including admin.
            // To limit it, remove 'Admin' and/or 'Staff' from the choices
            // in RegistrationFormType.php.
            $user->setRoles(match ($form->get('role')->getData()) {
                'admin' => ['ROLE_ADMIN'],
                'staff' => ['ROLE_STAFF'],
                default => [], // customer: only the automatic ROLE_USER
            });

            $entityManager->persist($user);
            $entityManager->flush();

            $this->addFlash('success', 'Account created! You can log in now.');

            return $this->redirectToRoute('app_login');
        }

        return $this->render('security/register.html.twig', [
            'registrationForm' => $form,
        ]);
    }
}