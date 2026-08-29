<?php

namespace App\Identity\Security;

use App\Api\Security\ActorProviderInterface;
use App\Identity\Entity\User;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;
use Symfony\Component\Uid\Uuid;

final class ActorProvider implements ActorProviderInterface
{
    public function __construct(
        private readonly Security $security,
    ) {
    }

    public function requireCurrentId(): Uuid
    {
        $user = $this->security->getUser();

        if (!$user instanceof User) {
            throw new AccessDeniedException();
        }

        return $user->getId();
    }
}
