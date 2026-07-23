<?php

namespace App\Tests\Application\Catalog;

use App\Catalog\Service\ProductThumbnailHasher;
use App\Tests\Application\ApiTestCase;
use League\Flysystem\FilesystemOperator;
use League\Flysystem\StorageAttributes;

final class ProductThumbnailHasherTest extends ApiTestCase
{
    private const string PNG_1X1 = 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==';

    protected function tearDown(): void
    {
        $storage = $this->storage();
        foreach ($storage->listContents('')->toArray() as $item) {
            \assert($item instanceof StorageAttributes);
            $item->isDir() ? $storage->deleteDirectory($item->path()) : $storage->delete($item->path());
        }

        parent::tearDown();
    }

    public function testHashesStoredThumbnail(): void
    {
        $bytes = base64_decode(self::PNG_1X1);
        $this->storage()->write('hasher/thumbnail.png', $bytes);

        self::assertSame(hash('sha256', $bytes), $this->hasher()->hashFor('hasher/thumbnail.png'));
    }

    public function testReturnsNullForMissingFile(): void
    {
        self::assertNull($this->hasher()->hashFor('does-not-exist.png'));
    }

    public function testReturnsNullForNullPath(): void
    {
        self::assertNull($this->hasher()->hashFor(null));
    }

    private function hasher(): ProductThumbnailHasher
    {
        $hasher = static::getContainer()->get(ProductThumbnailHasher::class);
        \assert($hasher instanceof ProductThumbnailHasher);

        return $hasher;
    }

    private function storage(): FilesystemOperator
    {
        $storage = static::getContainer()->get('product_thumbnails.storage');
        \assert($storage instanceof FilesystemOperator);

        return $storage;
    }
}
