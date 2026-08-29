<?php

namespace App\Identity\Api;

use App\Identity\Entity\User;
use Symfony\Component\Uid\Uuid;

interface UserRepositoryInterface
{
    public function findOneById(Uuid $id): ?User;

    public function findOneByEmail(string $email): ?User;

    public function save(User $user): void;
}
