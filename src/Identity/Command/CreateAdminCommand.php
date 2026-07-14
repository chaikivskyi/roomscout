<?php

namespace App\Identity\Command;

use App\Identity\Api\UserRepositoryInterface;
use App\Identity\Entity\User;
use App\Identity\Enum\Role;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Validator\ValidatorInterface;

#[AsCommand(
    name: 'identity:create-admin',
    description: 'Create (or promote with --promote) an admin user for the back office.',
)]
final class CreateAdminCommand extends Command
{
    public function __construct(
        private readonly UserRepositoryInterface $users,
        private readonly UserPasswordHasherInterface $passwordHasher,
        private readonly ValidatorInterface $validator,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('email', InputArgument::REQUIRED, 'Email address of the admin user')
            ->addOption('promote', null, InputOption::VALUE_NONE, 'Grant ROLE_ADMIN to an existing user (keeps their password)');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $email = $input->getArgument('email');

        $violations = $this->validator->validate($email, [new Assert\NotBlank(), new Assert\Email(), new Assert\Length(max: 180)]);

        if (count($violations) > 0) {
            $io->error(sprintf('Invalid email "%s": %s', $email, $violations[0]->getMessage()));

            return Command::FAILURE;
        }

        $existing = $this->users->findOneByEmail($email);

        if ($input->getOption('promote')) {
            if (null === $existing) {
                $io->error(sprintf('No user with email "%s" exists to promote. Run without --promote to create one.', $email));

                return Command::FAILURE;
            }

            if ($existing->hasRole(Role::Admin)) {
                $io->warning(sprintf('"%s" is already an admin — nothing to do.', $email));

                return Command::SUCCESS;
            }

            $existing->addRole(Role::Admin);
            $this->users->save($existing);

            $io->success(sprintf('Granted ROLE_ADMIN to "%s".', $email));

            return Command::SUCCESS;
        }

        if (null !== $existing) {
            $io->error(sprintf('User "%s" already exists. Re-run with --promote to grant ROLE_ADMIN.', $email));

            return Command::FAILURE;
        }

        if (!$input->isInteractive()) {
            $io->error('Creating a user requires an interactive terminal for the password prompt.');

            return Command::FAILURE;
        }

        $password = $io->askHidden('Password (min 8 characters)');
        $violations = $this->validator->validate($password, [new Assert\NotBlank(), new Assert\Length(min: 8, max: 4096)]);

        if (count($violations) > 0) {
            $io->error(sprintf('Invalid password: %s', $violations[0]->getMessage()));

            return Command::FAILURE;
        }

        if ($io->askHidden('Repeat password') !== $password) {
            $io->error('Passwords do not match.');

            return Command::FAILURE;
        }

        $user = new User();
        $user->setEmail($email);
        $user->setRoles([Role::Admin->value]);
        $user->setPassword($this->passwordHasher->hashPassword($user, $password));

        $this->users->save($user);

        $io->success(sprintf('Admin user "%s" created.', $email));

        return Command::SUCCESS;
    }
}
