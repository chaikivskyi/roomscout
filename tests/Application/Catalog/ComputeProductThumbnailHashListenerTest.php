<?php

namespace App\Tests\Application\Catalog;

use App\Tests\Application\ApiTestCase;
use App\Tests\Factory\ProductFactory;
use League\Flysystem\FilesystemOperator;
use League\Flysystem\StorageAttributes;

final class ComputeProductThumbnailHashListenerTest extends ApiTestCase
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

    public function testSetsHashOnInsert(): void
    {
        $bytes = base64_decode(self::PNG_1X1);
        $this->storage()->write('listener/thumbnail.png', $bytes);

        $product = ProductFactory::createOne(['thumbnailUrl' => 'listener/thumbnail.png']);

        self::assertSame(hash('sha256', $bytes), $product->getThumbnailHash());
    }

    public function testRecomputesHashOnUpdateWhenFileContentChanged(): void
    {
        $this->storage()->write('listener/replaced.png', base64_decode(self::PNG_1X1));
        $product = ProductFactory::createOne(['thumbnailUrl' => 'listener/replaced.png']);
        $originalHash = $product->getThumbnailHash();

        // Same path, new bytes — like a scraper re-download.
        $this->storage()->write('listener/replaced.png', 'new image bytes');
        $product->setTitle('renamed');
        $this->entityManager()->flush();

        self::assertNotSame($originalHash, $product->getThumbnailHash());
        self::assertSame(hash('sha256', 'new image bytes'), $product->getThumbnailHash());
    }

    public function testHashIsNullWhenThumbnailFileIsMissing(): void
    {
        $product = ProductFactory::createOne(['thumbnailUrl' => 'listener/nope.png']);

        self::assertNull($product->getThumbnailHash());
    }

    private function storage(): FilesystemOperator
    {
        $storage = static::getContainer()->get('product_thumbnails.storage');
        \assert($storage instanceof FilesystemOperator);

        return $storage;
    }
}
