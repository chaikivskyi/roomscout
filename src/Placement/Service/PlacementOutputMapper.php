<?php

namespace App\Placement\Service;

use App\Placement\ApiResource\PlacementOutput;
use App\Placement\Entity\ProductPlacement;
use League\Flysystem\FilesystemOperator;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

final class PlacementOutputMapper
{
    public function __construct(
        #[Autowire(service: 'project.storage')]
        private readonly FilesystemOperator $storage,
    ) {
    }

    public function map(ProductPlacement $placement): PlacementOutput
    {
        $resultVersion = $placement->getResultVersion();

        return new PlacementOutput(
            (string) $placement->getId(),
            $placement->getStatus()->value,
            null !== $placement->getContext() ? (string) $placement->getContext()->getId() : null,
            null !== $placement->getProduct() ? (string) $placement->getProduct()->getId() : null,
            $placement->getPrompt(),
            null !== $resultVersion ? (string) $resultVersion->getId() : null,
            null !== $resultVersion ? $this->storage->publicUrl($resultVersion->getImagePath()) : null,
            $placement->getCreatedAt(),
            $placement->getUpdatedAt(),
        );
    }
}
