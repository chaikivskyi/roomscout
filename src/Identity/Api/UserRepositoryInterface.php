<?php

namespace App\Identity\Api;

use App\Identity\Entity\User;

interface UserRepositoryInterface
{
    public function findOneByEmail(string $email): ?User;

    public function save(User $user): void;
}
