<?php

namespace App\Visualization\EventListener;

use App\Api\Bus\CommandBusInterface;
use App\Visualization\Command\GenerateVisualizationImage;
use App\Visualization\Entity\ProductVisualization;
use Doctrine\Bundle\DoctrineBundle\Attribute\AsEntityListener;
use Doctrine\ORM\Events;

#[AsEntityListener(event: Events::postPersist, method: 'postPersist', entity: ProductVisualization::class)]
final class DispatchGenerationOnVisualizationCreated
{
    public function __construct(
        private readonly CommandBusInterface $commandBus,
    ) {
    }

    public function postPersist(ProductVisualization $visualization): void
    {
        $this->commandBus->dispatch(new GenerateVisualizationImage((string) $visualization->getId()));
    }
}
