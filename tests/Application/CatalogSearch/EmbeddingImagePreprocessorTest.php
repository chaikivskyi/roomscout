<?php

namespace App\Tests\Application\CatalogSearch;

use App\CatalogSearch\Service\EmbeddingImagePreprocessor;
use PHPUnit\Framework\TestCase;

final class EmbeddingImagePreprocessorTest extends TestCase
{
    private const string PNG_1X1 = 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==';

    public function testSmallImagePassesThroughUntouched(): void
    {
        $bytes = base64_decode(self::PNG_1X1);

        [$mimeType, $result] = (new EmbeddingImagePreprocessor())->prepare('image/png', $bytes);

        self::assertSame('image/png', $mimeType);
        self::assertSame($bytes, $result);
    }

    public function testOversizedImageIsDownscaledToJpeg(): void
    {
        $image = imagecreatetruecolor(2048, 1024);
        imagefill($image, 0, 0, imagecolorallocate($image, 120, 40, 200));
        ob_start();
        imagepng($image);
        $bytes = (string) ob_get_clean();
        imagedestroy($image);

        [$mimeType, $result] = (new EmbeddingImagePreprocessor())->prepare('image/png', $bytes);

        self::assertSame('image/jpeg', $mimeType);
        $dimensions = getimagesizefromstring($result);
        self::assertNotFalse($dimensions);
        self::assertSame(1536, $dimensions[0]);
        self::assertSame(768, $dimensions[1]);
        self::assertSame('image/jpeg', $dimensions['mime']);
    }

    public function testDeclaredDecompressionBombPassesThroughUndecoded(): void
    {
        // 1x1 PNG with its IHDR patched to declare 100000x100000 pixels —
        // decoding that declaration would need ~50 GB of RAM.
        $bytes = base64_decode(self::PNG_1X1);
        $bytes = substr_replace($bytes, pack('N', 100000), 16, 4);
        $bytes = substr_replace($bytes, pack('N', 100000), 20, 4);

        [$mimeType, $result] = (new EmbeddingImagePreprocessor())->prepare('image/png', $bytes);

        self::assertSame('image/png', $mimeType);
        self::assertSame($bytes, $result);
    }

    public function testUndecodableBytesPassThrough(): void
    {
        $bytes = str_repeat('not an image at all ', 300000); // > 5 MB, not decodable

        [$mimeType, $result] = (new EmbeddingImagePreprocessor())->prepare('image/png', $bytes);

        self::assertSame('image/png', $mimeType);
        self::assertSame($bytes, $result);
    }
}
