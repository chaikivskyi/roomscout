<?php

namespace App\Identity\Command;

use App\Identity\Api\UserRepositoryInterface;
use App\Identity\Entity\User;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler(bus: 'command.bus')]
final class RegisterGuestHandler
{
    public function __construct(
        private readonly UserRepositoryInterface $users,
    ) {
    }

    public function __invoke(RegisterGuest $command): void
    {
        $this->users->save(new User($command->userId));
    }
}
