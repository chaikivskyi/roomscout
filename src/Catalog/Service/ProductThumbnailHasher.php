<?php

namespace App\Catalog\Service;

use League\Flysystem\FilesystemOperator;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Computes the content hash stored in Product::thumbnailHash.
 */
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

        return hash('sha256', $this->storage->read($path));
    }
}
