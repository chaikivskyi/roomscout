<?php

namespace App\Identity\Exception;

use App\Api\Exception\NotFoundException;
use Symfony\Component\Uid\Uuid;

final class UserNotFound extends NotFoundException
{
    private function __construct(string $message)
    {
        parent::__construct($message);
    }

    public static function byId(Uuid $id): self
    {
        return new self(sprintf('No user with id "%s" exists.', $id->toRfc4122()));
    }

    public static function byEmail(string $email): self
    {
        return new self(sprintf('No user with email "%s" exists.', $email));
    }
}
