<?php

namespace App\CatalogSearch\EventListener;

use App\Api\Bus\CommandBusInterface;
use App\CatalogSearch\Command\MatchContextProducts;
use App\Project\Entity\ProjectContext;
use Doctrine\Bundle\DoctrineBundle\Attribute\AsEntityListener;
use Doctrine\ORM\Events;

#[AsEntityListener(event: Events::postPersist, method: 'postPersist', entity: ProjectContext::class)]
final class DispatchMatchingOnContextCreated
{
    public function __construct(
        private readonly CommandBusInterface $commandBus,
    ) {
    }

    public function postPersist(ProjectContext $context): void
    {
        $this->commandBus->dispatch(new MatchContextProducts((string) $context->getId()));
    }
}
