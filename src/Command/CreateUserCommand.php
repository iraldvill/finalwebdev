<?php

namespace App\Command;

use App\Entity\User;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

#[AsCommand(
    name: 'app:create-user',
    description: 'Create a user, or reset the password of an existing one (the password is always hashed)',
)]
class CreateUserCommand extends Command
{
    public function __construct(
        private EntityManagerInterface $em,
        private UserPasswordHasherInterface $hasher,
        private UserRepository $users,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('email', InputArgument::REQUIRED, 'Login email')
            ->addArgument('fullName', InputArgument::OPTIONAL, 'Full name (asked if the user is new)')
            ->addOption('role', null, InputOption::VALUE_REQUIRED, 'admin or staff', 'staff');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $email = (string) $input->getArgument('email');
        $role = strtolower((string) $input->getOption('role'));
        if (!in_array($role, ['admin', 'staff'], true)) {
            $io->error('The role must be "admin" or "staff".');

            return Command::INVALID;
        }

        $user = $this->users->findOneBy(['email' => $email]);
        $isNew = null === $user;

        $fullName = $input->getArgument('fullName');
        if ($isNew) {
            $fullName = $fullName ?? $io->ask('Full name', null, function ($value) {
                if (null === $value || '' === trim($value)) {
                    throw new \RuntimeException('The name cannot be empty.');
                }

                return trim($value);
            });
            $user = (new User())->setEmail($email)->setFullName($fullName);
        } elseif ($fullName) {
            $user->setFullName($fullName);
        }

        $password = $io->askHidden('Password (at least 8 characters, hidden while typing)', function ($value) {
            if (null === $value || strlen($value) < 8) {
                throw new \RuntimeException('The password must be at least 8 characters.');
            }

            return $value;
        });

        $user->setRoles(['admin' === $role ? 'ROLE_ADMIN' : 'ROLE_STAFF']);
        $user->setPassword($this->hasher->hashPassword($user, $password));

        if ($isNew) {
            $this->em->persist($user);
        }
        $this->em->flush();

        $io->success(sprintf('%s %s as %s.', $isNew ? 'Created' : 'Updated', $email, strtoupper($role)));

        return Command::SUCCESS;
    }
}