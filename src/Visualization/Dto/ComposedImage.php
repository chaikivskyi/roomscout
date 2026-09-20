<?php

namespace App\Visualization\Dto;

final class ComposedImage
{
    public function __construct(
        public readonly string $mimeType,
        public readonly string $bytes,
    ) {
    }
}
