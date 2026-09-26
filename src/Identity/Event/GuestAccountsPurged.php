<?php

namespace App\Identity\Event;

use Symfony\Component\Uid\Uuid;

final class GuestAccountsPurged
{
    /**
     * @param list<Uuid> $userIds
     */
    public function __construct(
        public readonly array $userIds,
    ) {
    }
}
