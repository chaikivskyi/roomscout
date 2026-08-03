<?php

namespace App\Placement\Service;

use App\Placement\Dto\ComposedImage;
use App\Placement\Exception\ImageGenerationException;

interface ProductImageComposerInterface
{
    /**
     * @throws ImageGenerationException
     */
    public function compose(
        string $prompt,
        string $roomMimeType,
        string $roomBytes,
        string $productMimeType,
        string $productBytes,
    ): ComposedImage;
}
