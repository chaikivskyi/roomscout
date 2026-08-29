<?php

namespace App\Identity\Query;

use App\Identity\Api\UserRepositoryInterface;
use App\Identity\Entity\User;
use App\Identity\Exception\UserNotFound;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler(bus: 'query.bus')]
final class GetUserHandler
{
    public function __construct(
        private readonly UserRepositoryInterface $users,
    ) {
    }

    public function __invoke(GetUser $query): User
    {
        return $this->users->findOneById($query->userId) ?? throw UserNotFound::byId($query->userId);
    }
}
