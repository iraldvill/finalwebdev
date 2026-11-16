<?php

namespace App\Controller;

use App\Entity\User;
use App\Form\UserFormType;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\FormError;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Admin only: add, edit, disable and delete accounts.
 * The list of accounts is the "Accounts" tab on the dashboard.
 */
#[Route('/admin/users')]
#[IsGranted('ROLE_ADMIN')]
class AdminUserController extends AbstractController
{
    #[Route('/new', name: 'app_admin_user_new', methods: ['GET', 'POST'])]
    public function new(
        Request $request,
        EntityManagerInterface $em,
        UserPasswordHasherInterface $hasher,
    ): Response {
        $user = new User();
        $form = $this->createForm(UserFormType::class, $user, ['is_new' => true]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->applyRoleAndPassword($user, $form, $hasher);

            $em->persist($user);
            $em->flush();

            $this->addFlash('success', sprintf('Account "%s" was created.', $user->getFullName()));

            return $this->backToAccounts();
        }

        return $this->render('admin/user_form.html.twig', [
            'form' => $form,
            'title' => 'Add Account',
            'is_new' => true,
        ]);
    }

    #[Route('/{id}/edit', name: 'app_admin_user_edit', requirements: ['id' => '\d+'], methods: ['GET', 'POST'])]
    public function edit(
        User $user,
        Request $request,
        EntityManagerInterface $em,
        UserPasswordHasherInterface $hasher,
    ): Response {
        $form = $this->createForm(UserFormType::class, $user, [
            'is_new' => false,
            'current_role' => $this->roleKey($user),
        ]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            // Don't let an admin lock themselves out.
            if ($this->isCurrentUser($user)) {
                if ('admin' !== $form->get('role')->getData()) {
                    $form->get('role')->addError(new FormError('You cannot remove your own admin role.'));
                }
                if (!$user->isActive()) {
                    $form->get('active')->addError(new FormError('You cannot disable your own account.'));
                }
            }

            if ($form->isValid()) {
                $this->applyRoleAndPassword($user, $form, $hasher);
                $em->flush();

                $this->addFlash('success', sprintf('Account "%s" was updated.', $user->getFullName()));

                return $this->backToAccounts();
            }
        }

        return $this->render('admin/user_form.html.twig', [
            'form' => $form,
            'title' => 'Edit Account',
            'is_new' => false,
        ]);
    }

    #[Route('/{id}/toggle', name: 'app_admin_user_toggle', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function toggle(User $user, Request $request, EntityManagerInterface $em): Response
    {
        if (!$this->isCsrfTokenValid('toggle-user-'.$user->getId(), $request->request->getString('_token'))) {
            $this->addFlash('danger', 'Invalid security token. Please try again.');

            return $this->backToAccounts();
        }

        if ($this->isCurrentUser($user)) {
            $this->addFlash('danger', 'You cannot disable the account you are logged in with.');

            return $this->backToAccounts();
        }

        $user->setActive(!$user->isActive());
        $em->flush();

        $this->addFlash('success', sprintf(
            'Account "%s" was %s.',
            $user->getFullName(),
            $user->isActive() ? 'enabled' : 'disabled',
        ));

        return $this->backToAccounts();
    }

    #[Route('/{id}/delete', name: 'app_admin_user_delete', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function delete(User $user, Request $request, EntityManagerInterface $em): Response
    {
        if (!$this->isCsrfTokenValid('delete-user-'.$user->getId(), $request->request->getString('_token'))) {
            $this->addFlash('danger', 'Invalid security token. Please try again.');

            return $this->backToAccounts();
        }

        if ($this->isCurrentUser($user)) {
            $this->addFlash('danger', 'You cannot delete the account you are logged in with.');

            return $this->backToAccounts();
        }

        $name = $user->getFullName();
        // Their bookings and reviews are removed with them (orphanRemoval on the entity).
        $em->remove($user);
        $em->flush();

        $this->addFlash('success', sprintf('Account "%s" was deleted.', $name));

        return $this->backToAccounts();
    }

    private function backToAccounts(): Response
    {
        // The #accounts part opens the Accounts tab on the dashboard.
        return $this->redirect($this->generateUrl('app_dashboard').'#accounts');
    }

    private function isCurrentUser(User $user): bool
    {
        $current = $this->getUser();

        return $current instanceof User && $current->getId() === $user->getId();
    }

    private function roleKey(User $user): string
    {
        return match (true) {
            in_array('ROLE_ADMIN', $user->getRoles(), true) => 'admin',
            in_array('ROLE_STAFF', $user->getRoles(), true) => 'staff',
            default => 'customer',
        };
    }

    private function applyRoleAndPassword(User $user, FormInterface $form, UserPasswordHasherInterface $hasher): void
    {
        $user->setRoles(match ($form->get('role')->getData()) {
            'admin' => ['ROLE_ADMIN'],
            'staff' => ['ROLE_STAFF'],
            default => [], // customer: only the automatic ROLE_USER
        });

        // Only set a password when one was typed (blank on edit = keep the old one).
        $plainPassword = $form->get('plainPassword')->getData();
        if (null !== $plainPassword && '' !== $plainPassword) {
            $user->setPassword($hasher->hashPassword($user, $plainPassword));
        }
    }
}