<?php

namespace App\Identity\Command;

use App\Identity\Api\UserRepositoryInterface;
use App\Identity\Enum\Role;
use App\Identity\Exception\UserNotFound;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler(bus: 'command.bus')]
final class PromoteUserToAdminHandler
{
    public function __construct(
        private readonly UserRepositoryInterface $users,
    ) {
    }

    public function __invoke(PromoteUserToAdmin $command): void
    {
        $user = $this->users->findOneByEmail($command->email) ?? throw UserNotFound::byEmail($command->email);

        if ($user->hasRole(Role::Admin)) {
            return;
        }

        $user->addRole(Role::Admin);
        $this->users->save($user);
    }
}
