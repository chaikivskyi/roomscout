<?php

namespace App\Identity\Api;

use App\Identity\Entity\User;
use Symfony\Component\Uid\Uuid;

interface UserRepositoryInterface
{
    public function findOneById(Uuid $id): ?User;

    public function findOneByEmail(string $email): ?User;

    public function save(User $user): void;

    public function updateLastActiveAt(Uuid $id, \DateTimeImmutable $at): void;

    /**
     * @return list<Uuid>
     */
    public function findStaleGuestIds(\DateTimeImmutable $idleBefore, \DateTimeImmutable $staleBefore, int $limit): array;

    /**
     * @param list<Uuid> $ids
     *
     * @return list<Uuid>
     */
    public function deleteByIds(array $ids): array;
}
