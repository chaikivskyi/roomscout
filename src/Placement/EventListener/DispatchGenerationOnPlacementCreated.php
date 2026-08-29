<?php

namespace App\Placement\EventListener;

use App\Api\Bus\CommandBusInterface;
use App\Placement\Command\GeneratePlacementImage;
use App\Placement\Entity\ProductPlacement;
use Doctrine\Bundle\DoctrineBundle\Attribute\AsEntityListener;
use Doctrine\ORM\Events;

#[AsEntityListener(event: Events::postPersist, method: 'postPersist', entity: ProductPlacement::class)]
final class DispatchGenerationOnPlacementCreated
{
    public function __construct(
        private readonly CommandBusInterface $commandBus,
    ) {
    }

    public function postPersist(ProductPlacement $placement): void
    {
        $this->commandBus->dispatch(new GeneratePlacementImage((string) $placement->getId()));
    }
}
