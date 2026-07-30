<?php

namespace App\CatalogSearch\EventListener;

use App\CatalogSearch\Message\MatchContextProductsMessage;
use App\Project\Entity\ProjectContext;
use Doctrine\Bundle\DoctrineBundle\Attribute\AsEntityListener;
use Doctrine\ORM\Events;
use Symfony\Component\Messenger\MessageBusInterface;

#[AsEntityListener(event: Events::postPersist, method: 'postPersist', entity: ProjectContext::class)]
final class DispatchMatchingOnContextCreated
{
    public function __construct(
        private readonly MessageBusInterface $messageBus,
    ) {
    }

    public function postPersist(ProjectContext $context): void
    {
        $this->messageBus->dispatch(new MatchContextProductsMessage((string) $context->getId()));
    }
}
