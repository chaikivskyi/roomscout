<?php

namespace App\Admin\EventListener;

use App\Identity\Entity\User;
use App\Identity\Enum\Role;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

/**
 * In prod, an admin without an enrolled TOTP secret would silently skip the
 * 2FA challenge (the bundle only challenges enrolled users). This guard makes
 * enrollment mandatory: un-enrolled admins can only reach the setup page.
 */
final class RequireTotpEnrollmentSubscriber implements EventSubscriberInterface
{
    private const string SETUP_ROUTE = 'admin_2fa_setup';

    public function __construct(
        private readonly Security $security,
        private readonly UrlGeneratorInterface $urlGenerator,
        #[Autowire('%kernel.environment%')]
        private readonly string $environment,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [KernelEvents::REQUEST => 'onKernelRequest'];
    }

    public function onKernelRequest(RequestEvent $event): void
    {
        if (!$event->isMainRequest() || 'prod' !== $this->environment) {
            return;
        }

        $request = $event->getRequest();

        if (!str_starts_with($request->getPathInfo(), '/admin') || self::SETUP_ROUTE === $request->attributes->get('_route')) {
            return;
        }

        $user = $this->security->getUser();

        if (!$user instanceof User || null !== $user->getTotpSecret() || !$this->security->isGranted(Role::Admin->value)) {
            return;
        }

        $event->setResponse(new RedirectResponse($this->urlGenerator->generate(self::SETUP_ROUTE)));
    }
}
