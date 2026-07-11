<?php

namespace App\Identity\EventListener;

use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Http\Event\LogoutEvent;

/**
 * Replaces the default logout redirect with an API-friendly 204.
 * Priority is above DefaultLogoutListener (64); Lexik's BlockJWTListener (0)
 * still runs afterwards and blocklists the token.
 */
#[AsEventListener(event: LogoutEvent::class, priority: 100)]
final class LogoutResponseListener
{
    public function __invoke(LogoutEvent $event): void
    {
        if (!str_starts_with($event->getRequest()->getPathInfo(), '/api/')) {
            return;
        }

        $event->setResponse(new JsonResponse(null, Response::HTTP_NO_CONTENT));
    }
}
