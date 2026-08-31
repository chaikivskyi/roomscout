<?php

namespace App\Placement\Service;

use App\Placement\ApiResource\PlacementOutput;
use App\Placement\Entity\ProductPlacement;
use App\Project\Service\ProjectImageUrlResolver;

final class PlacementOutputMapper
{
    public function __construct(
        private readonly ProjectImageUrlResolver $imageUrls,
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
            $this->imageUrls->resolve($resultVersion?->getImagePath()),
            $placement->getCreatedAt(),
            $placement->getUpdatedAt(),
        );
    }
}
