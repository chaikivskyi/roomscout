<?php

namespace App\Tests\Integration\CatalogSearch;

use App\CatalogSearch\Entity\ProductEmbedding;
use App\CatalogSearch\Service\CohereImageEmbedder;
use App\Tests\Fake\FakeImageEmbedder;
use App\Tests\Integration\IntegrationTestCase;
use PHPUnit\Framework\Attributes\Group;

#[Group('integration')]
final class CohereImageEmbedderIntegrationTest extends IntegrationTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        self::requireApiKey('COHERE_API_KEY');
    }

    public function testEmbedImageReturnsAFullLengthVector(): void
    {
        $vector = $this->embedder()->embedImage('image/png', self::sampleImage());

        self::assertCount(ProductEmbedding::DIMENSIONS, $vector);
        self::assertNotEquals(array_fill(0, ProductEmbedding::DIMENSIONS, 0.0), $vector);

        foreach ($vector as $value) {
            self::assertTrue(is_finite($value), 'pgvector rejects NaN and infinity.');
        }
    }

    public function testEmbedQueryReturnsAFullLengthVector(): void
    {
        $vector = $this->embedder()->embedQuery(
            'a walnut coffee table in a bright living room',
            'image/png',
            self::sampleImage(),
        );

        self::assertCount(ProductEmbedding::DIMENSIONS, $vector);
        self::assertNotEquals(array_fill(0, ProductEmbedding::DIMENSIONS, 0.0), $vector);

        foreach ($vector as $value) {
            self::assertTrue(is_finite($value), 'pgvector rejects NaN and infinity.');
        }
    }

    public function testModelMatchesTheOneStampedOnStoredEmbeddings(): void
    {
        self::assertSame(FakeImageEmbedder::MODEL, $this->embedder()->model(), 'The fake stamps this model onto embeddings in every other test.');
    }

    private function embedder(): CohereImageEmbedder
    {
        $embedder = static::getContainer()->get(CohereImageEmbedder::class);
        self::assertInstanceOf(CohereImageEmbedder::class, $embedder);

        return $embedder;
    }

    private static function sampleImage(): string
    {
        $image = imagecreatetruecolor(64, 64);
        imagefill($image, 0, 0, (int) imagecolorallocate($image, 210, 180, 140));
        imagefilledrectangle($image, 8, 32, 56, 56, (int) imagecolorallocate($image, 90, 60, 40));
        ob_start();
        imagepng($image);
        $bytes = (string) ob_get_clean();
        imagedestroy($image);

        return $bytes;
    }
}
