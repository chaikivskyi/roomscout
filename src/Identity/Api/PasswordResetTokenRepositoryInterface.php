<?php

namespace App\Identity\Api;

use App\Identity\Entity\PasswordResetToken;
use App\Identity\Entity\User;

interface PasswordResetTokenRepositoryInterface
{
    public function findOneByTokenHash(string $tokenHash): ?PasswordResetToken;

    public function deleteForUser(User $user): void;

    public function save(PasswordResetToken $token): void;

    public function delete(PasswordResetToken $token): void;
}
