<?php

namespace App\Placement\Dto;

final class ComposedImage
{
    public function __construct(
        public readonly string $mimeType,
        public readonly string $bytes,
    ) {
    }
}
