<?php

namespace App\Catalog\Service;

use League\Flysystem\FilesystemOperator;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

final class ProductThumbnailHasher
{
    public function __construct(
        #[Autowire(service: 'product_thumbnails.storage')]
        private readonly FilesystemOperator $storage,
    ) {
    }

    public function hashFor(?string $path): ?string
    {
        if (null === $path || !$this->storage->fileExists($path)) {
            return null;
        }

        return $this->hashBytes($this->storage->read($path));
    }

    public function hashBytes(string $bytes): string
    {
        return hash('sha256', $bytes);
    }
}
