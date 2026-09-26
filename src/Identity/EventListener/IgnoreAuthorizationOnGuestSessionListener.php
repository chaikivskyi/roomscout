<?php

namespace App\Identity\EventListener;

use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;

#[AsEventListener(event: KernelEvents::REQUEST, priority: 250)]
final class IgnoreAuthorizationOnGuestSessionListener
{
    private const string PATH = '/api/guest';

    public function __invoke(RequestEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $request = $event->getRequest();

        $path = rawurldecode($request->getPathInfo());

        if (Request::METHOD_POST !== $request->getMethod() || self::PATH !== $path) {
            return;
        }

        $request->headers->remove('Authorization');
    }
}
