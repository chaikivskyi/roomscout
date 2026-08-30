<?php

namespace App\Tests\Fake;

use App\Placement\Dto\ComposedImage;
use App\Placement\Service\ProductImageComposerInterface;

final class FakeProductImageComposer implements ProductImageComposerInterface
{
    private const string RESULT_PNG = 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNk+M9QDwADhgGAWjR9awAAAABJRU5ErkJggg==';

    /**
     * @var list<ComposedImage|\Throwable>
     */
    private array $queue = [];

    /**
     * @var list<array{prompt: string, roomMimeType: string, roomBytes: string, productMimeType: string, productBytes: string}>
     */
    private array $calls = [];

    public static function defaultImage(): ComposedImage
    {
        return new ComposedImage('image/png', base64_decode(self::RESULT_PNG));
    }

    public function willReturn(ComposedImage $image): self
    {
        $this->queue[] = $image;

        return $this;
    }

    public function willThrow(\Throwable $error): self
    {
        $this->queue[] = $error;

        return $this;
    }

    public function compose(
        string $prompt,
        string $roomMimeType,
        string $roomBytes,
        string $productMimeType,
        string $productBytes,
    ): ComposedImage {
        $this->calls[] = [
            'prompt' => $prompt,
            'roomMimeType' => $roomMimeType,
            'roomBytes' => $roomBytes,
            'productMimeType' => $productMimeType,
            'productBytes' => $productBytes,
        ];

        $queued = array_shift($this->queue);

        if ($queued instanceof \Throwable) {
            throw $queued;
        }

        return $queued ?? self::defaultImage();
    }

    /**
     * @return list<array{prompt: string, roomMimeType: string, roomBytes: string, productMimeType: string, productBytes: string}>
     */
    public function calls(): array
    {
        return $this->calls;
    }

    public function callCount(): int
    {
        return \count($this->calls);
    }
}
