<?php

namespace App\Visualization\Service;

use App\Visualization\Dto\ComposedImage;
use App\Visualization\Exception\ImageGenerationException;

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
