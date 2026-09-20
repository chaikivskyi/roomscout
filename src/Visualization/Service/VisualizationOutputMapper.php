<?php

namespace App\Visualization\Service;

use App\Visualization\ApiResource\VisualizationOutput;
use App\Visualization\Entity\ProductVisualization;
use App\Project\Service\ProjectImageUrlResolver;

final class VisualizationOutputMapper
{
    public function __construct(
        private readonly ProjectImageUrlResolver $imageUrls,
    ) {
    }

    public function map(ProductVisualization $visualization): VisualizationOutput
    {
        $resultVersion = $visualization->getResultVersion();

        return new VisualizationOutput(
            (string) $visualization->getId(),
            $visualization->getStatus()->value,
            null !== $visualization->getContext() ? (string) $visualization->getContext()->getId() : null,
            null !== $visualization->getProduct() ? (string) $visualization->getProduct()->getId() : null,
            $visualization->getPrompt(),
            null !== $resultVersion ? (string) $resultVersion->getId() : null,
            $this->imageUrls->resolve($resultVersion?->getImagePath()),
            $visualization->getCreatedAt(),
            $visualization->getUpdatedAt(),
        );
    }
}
