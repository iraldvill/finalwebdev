<?php

namespace App\Controller;

use App\Entity\User;
use App\Form\RegistrationFormType;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Form\FormError;
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
        #[Autowire(env: 'REGISTRATION_CODE_ADMIN')] string $adminCode,
        #[Autowire(env: 'REGISTRATION_CODE_STAFF')] string $staffCode,
    ): Response {
        // Already logged in? Nothing to register.
        if ($this->getUser()) {
            return $this->redirectToRoute('app_home');
        }

        $user = new User();
        $form = $this->createForm(RegistrationFormType::class, $user);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $role = $form->get('role')->getData();
            $code = trim((string) $form->get('registrationCode')->getData());

            // Customers need no code. Staff and admin need the matching secret code,
            // so nobody can make themselves an admin just by picking it in the form.
            $expectedCode = match ($role) {
                'admin' => $adminCode,
                'staff' => $staffCode,
                default => null,
            };

            if (null !== $expectedCode && ('' === $expectedCode || !hash_equals($expectedCode, $code))) {
                $form->get('registrationCode')->addError(new FormError('That registration code is not valid.'));
            } else {
                // Hash the password. The plain text is never saved.
                $plainPassword = $form->get('plainPassword')->getData();
                $user->setPassword($passwordHasher->hashPassword($user, $plainPassword));

                $user->setRoles(match ($role) {
                    'admin' => ['ROLE_ADMIN'],
                    'staff' => ['ROLE_STAFF'],
                    default => [], // customer: only the automatic ROLE_USER
                });

                $entityManager->persist($user);
                $entityManager->flush();

                $this->addFlash('success', 'Account created! You can log in now.');

                return $this->redirectToRoute('app_login');
            }
        }

        return $this->render('security/register.html.twig', [
            'registrationForm' => $form,
        ]);
    }
}