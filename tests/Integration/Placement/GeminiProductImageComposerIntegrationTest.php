<?php

namespace App\Tests\Integration\Placement;

use App\Placement\Service\GeminiProductImageComposer;
use App\Project\Service\ProjectImageStorage;
use App\Tests\Integration\IntegrationTestCase;
use PHPUnit\Framework\Attributes\Group;

#[Group('integration')]
final class GeminiProductImageComposerIntegrationTest extends IntegrationTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        self::requireApiKey('GEMINI_API_KEY');
    }

    public function testComposeReturnsAnEditedImage(): void
    {
        $image = $this->composer()->compose(
            'place the wooden side table against the back wall',
            'image/png',
            self::roomImage(),
            'image/png',
            self::productImage(),
        );

        self::assertArrayHasKey(
            $image->mimeType,
            ProjectImageStorage::EXTENSIONS,
            'ProjectImageStorage::storeBytes() maps only these mime types; anything else throws and the placement retries into failed.',
        );
        self::assertNotSame('', $image->bytes);

        $dimensions = getimagesizefromstring($image->bytes);
        self::assertNotFalse($dimensions, 'The returned bytes must decode as an image.');
        self::assertGreaterThan(0, $dimensions[0]);
        self::assertGreaterThan(0, $dimensions[1]);
    }

    private function composer(): GeminiProductImageComposer
    {
        $composer = static::getContainer()->get(GeminiProductImageComposer::class);
        self::assertInstanceOf(GeminiProductImageComposer::class, $composer);

        return $composer;
    }

    private static function roomImage(): string
    {
        $image = imagecreatetruecolor(512, 384);
        imagefill($image, 0, 0, (int) imagecolorallocate($image, 236, 232, 224));
        imagefilledrectangle($image, 0, 256, 511, 383, (int) imagecolorallocate($image, 178, 148, 112));
        imagefilledrectangle($image, 330, 60, 470, 240, (int) imagecolorallocate($image, 210, 230, 245));

        ob_start();
        imagepng($image);
        $bytes = (string) ob_get_clean();
        imagedestroy($image);

        return $bytes;
    }

    private static function productImage(): string
    {
        $image = imagecreatetruecolor(192, 192);
        imagefill($image, 0, 0, (int) imagecolorallocate($image, 255, 255, 255));
        imagefilledrectangle($image, 40, 70, 152, 90, (int) imagecolorallocate($image, 120, 80, 45));
        imagefilledrectangle($image, 48, 90, 60, 150, (int) imagecolorallocate($image, 120, 80, 45));
        imagefilledrectangle($image, 132, 90, 144, 150, (int) imagecolorallocate($image, 120, 80, 45));

        ob_start();
        imagepng($image);
        $bytes = (string) ob_get_clean();
        imagedestroy($image);

        return $bytes;
    }
}
