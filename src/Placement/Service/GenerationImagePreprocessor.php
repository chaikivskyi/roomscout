<?php

namespace App\Placement\Service;

use App\CatalogSearch\Service\EmbeddingImagePreprocessor;

final class GenerationImagePreprocessor
{
    public function __construct(
        private readonly EmbeddingImagePreprocessor $inner,
    ) {
    }

    /**
     * @return array{0: string, 1: string} [mimeType, imageBytes]
     */
    public function prepare(string $mimeType, string $imageBytes): array
    {
        return $this->inner->prepare($mimeType, $imageBytes);
    }
}
