<?php

namespace App\CatalogSearch\Service;

final class EmbeddingImagePreprocessor
{
    private const MAX_BYTES = 5 * 1024 * 1024;
    private const MAX_DIMENSION = 1536;
    private const JPEG_QUALITY = 85;

    private const MAX_PIXELS = 25_000_000;

    /**
     * @return array{0: string, 1: string} [mimeType, imageBytes]
     */
    public function prepare(string $mimeType, string $imageBytes): array
    {
        $dimensions = @getimagesizefromstring($imageBytes);
        $exceedsDimensions = false !== $dimensions && max($dimensions[0], $dimensions[1]) > self::MAX_DIMENSION;

        if (\strlen($imageBytes) <= self::MAX_BYTES && !$exceedsDimensions) {
            return [$mimeType, $imageBytes];
        }

        if (false !== $dimensions && $dimensions[0] * $dimensions[1] > self::MAX_PIXELS) {
            return [$mimeType, $imageBytes];
        }

        $source = @imagecreatefromstring($imageBytes);

        if (false === $source) {
            return [$mimeType, $imageBytes];
        }

        $width = imagesx($source);
        $height = imagesy($source);
        $scale = min(1.0, self::MAX_DIMENSION / max($width, $height));
        $targetWidth = max(1, (int) round($width * $scale));
        $targetHeight = max(1, (int) round($height * $scale));

        $target = imagecreatetruecolor($targetWidth, $targetHeight);
        imagefill($target, 0, 0, (int) imagecolorallocate($target, 255, 255, 255));

        if ($scale < 1.0) {
            imagecopyresampled($target, $source, 0, 0, 0, 0, $targetWidth, $targetHeight, $width, $height);
        } else {
            imagecopy($target, $source, 0, 0, 0, 0, $width, $height);
        }

        ob_start();
        imagejpeg($target, null, self::JPEG_QUALITY);

        return ['image/jpeg', (string) ob_get_clean()];
    }
}
