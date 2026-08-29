<?php

namespace App\Identity\Query;

use App\Api\Bus\QueryInterface;
use App\Identity\Entity\User;
use Symfony\Component\Uid\Uuid;

/**
 * @implements QueryInterface<User>
 */
final class GetUser implements QueryInterface
{
    public function __construct(
        public readonly Uuid $userId,
    ) {
    }
}
