<?php

namespace App\Tests\Application\Catalog;

use App\Tests\Application\ApiTestCase;
use App\Tests\Factory\ProductFactory;
use League\Flysystem\FilesystemOperator;

final class ComputeProductThumbnailHashListenerTest extends ApiTestCase
{
    private const string PNG_1X1 = 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==';

    protected function tearDown(): void
    {
        $storage = $this->storage();
        foreach ($storage->listContents('')->toArray() as $item) {
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

    public function testKeepsHashProvidedOnInsert(): void
    {
        $this->storage()->write('listener/preset.png', base64_decode(self::PNG_1X1));

        $product = ProductFactory::createOne([
            'thumbnailUrl' => 'listener/preset.png',
            'thumbnailHash' => 'hash-set-by-write-path',
        ]);

        self::assertSame('hash-set-by-write-path', $product->getThumbnailHash());
    }

    public function testDoesNotRehashWhenThumbnailPathUnchanged(): void
    {
        $this->storage()->write('listener/replaced.png', base64_decode(self::PNG_1X1));
        $product = ProductFactory::createOne(['thumbnailUrl' => 'listener/replaced.png']);
        $originalHash = $product->getThumbnailHash();

        $this->storage()->write('listener/replaced.png', 'new image bytes');
        $product->setTitle('renamed');
        $this->entityManager()->flush();

        self::assertSame($originalHash, $product->getThumbnailHash());
    }

    public function testRecomputesHashWhenThumbnailPathChanges(): void
    {
        $this->storage()->write('listener/first.png', base64_decode(self::PNG_1X1));
        $product = ProductFactory::createOne(['thumbnailUrl' => 'listener/first.png']);

        $this->storage()->write('listener/second.png', 'other image bytes');
        $product->setThumbnailUrl('listener/second.png');
        $this->entityManager()->flush();

        self::assertSame(hash('sha256', 'other image bytes'), $product->getThumbnailHash());
    }

    public function testKeepsHashProvidedAlongsidePathChange(): void
    {
        $this->storage()->write('listener/first.png', base64_decode(self::PNG_1X1));
        $product = ProductFactory::createOne(['thumbnailUrl' => 'listener/first.png']);

        $this->storage()->write('listener/second.png', 'other image bytes');
        $product->setThumbnailUrl('listener/second.png');
        $product->setThumbnailHash('hash-set-by-write-path');
        $this->entityManager()->flush();

        self::assertSame('hash-set-by-write-path', $product->getThumbnailHash());
    }

    public function testHashIsNullWhenThumbnailFileIsMissing(): void
    {
        $product = ProductFactory::createOne(['thumbnailUrl' => 'listener/nope.png']);

        self::assertNull($product->getThumbnailHash());
    }

    public function testBackfillsNullHashOnLaterUpdateOnceFileExists(): void
    {
        $product = ProductFactory::createOne(['thumbnailUrl' => 'listener/late.png']);
        self::assertNull($product->getThumbnailHash());

        $this->storage()->write('listener/late.png', 'late bytes');
        $product->setTitle('renamed');
        $this->entityManager()->flush();

        self::assertSame(hash('sha256', 'late bytes'), $product->getThumbnailHash());
    }

    private function storage(): FilesystemOperator
    {
        return static::getContainer()->get('product_thumbnails.storage');
    }
}
