<?php

namespace App\Api\Security;

use Symfony\Component\Uid\Uuid;

interface ActorProviderInterface
{
    /**
     * @throws \Symfony\Component\Security\Core\Exception\AccessDeniedException when there is no authenticated actor
     */
    public function requireCurrentId(): Uuid;
}
