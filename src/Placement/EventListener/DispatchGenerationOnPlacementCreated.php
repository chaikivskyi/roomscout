<?php

namespace App\Placement\EventListener;

use App\Placement\Entity\ProductPlacement;
use App\Placement\Message\GeneratePlacementImageMessage;
use Doctrine\Bundle\DoctrineBundle\Attribute\AsEntityListener;
use Doctrine\ORM\Events;
use Symfony\Component\Messenger\MessageBusInterface;

#[AsEntityListener(event: Events::postPersist, method: 'postPersist', entity: ProductPlacement::class)]
final class DispatchGenerationOnPlacementCreated
{
    public function __construct(
        private readonly MessageBusInterface $messageBus,
    ) {
    }

    public function postPersist(ProductPlacement $placement): void
    {
        $this->messageBus->dispatch(new GeneratePlacementImageMessage((string) $placement->getId()));
    }
}
