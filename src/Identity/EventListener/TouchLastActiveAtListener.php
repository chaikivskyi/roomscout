<?php

namespace App\Identity\EventListener;

use App\Identity\Api\UserRepositoryInterface;
use App\Identity\Entity\User;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpKernel\Event\TerminateEvent;
use Symfony\Component\HttpKernel\KernelEvents;

#[AsEventListener(event: KernelEvents::TERMINATE)]
final class TouchLastActiveAtListener
{
    public const int THROTTLE_SECONDS = 900;

    public function __construct(
        private readonly Security $security,
        private readonly UserRepositoryInterface $users,
        private readonly LoggerInterface $logger,
    ) {
    }

    public function __invoke(TerminateEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $user = $this->security->getUser();

        if (!$user instanceof User) {
            return;
        }

        $now = new \DateTimeImmutable();
        $neverSeenSince = $user->getLastActiveAt()->getTimestamp() === $user->getCreatedAt()->getTimestamp();

        if (!$neverSeenSince && $now->getTimestamp() - $user->getLastActiveAt()->getTimestamp() < self::THROTTLE_SECONDS) {
            return;
        }

        $user->touchLastActiveAt($now);

        try {
            $this->users->updateLastActiveAt($user->getId(), $now);
        } catch (\Throwable $e) {
            $this->logger->error('Could not record user activity.', [
                'user' => (string) $user->getId(),
                'exception' => $e,
            ]);
        }
    }
}
