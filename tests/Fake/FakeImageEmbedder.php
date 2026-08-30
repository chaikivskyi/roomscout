<?php

namespace App\Tests\Fake;

use App\CatalogSearch\Entity\ProductEmbedding;
use App\CatalogSearch\Service\ImageEmbedderInterface;

final class FakeImageEmbedder implements ImageEmbedderInterface
{
    public const string MODEL = 'embed-v4.0';

    /**
     * @var list<list<float>|\Throwable>
     */
    private array $queue = [];

    /**
     * @var list<array{type: string, prompt: ?string, mimeType: string, bytes: string}>
     */
    private array $calls = [];

    /**
     * @param list<float> $vector zero-padded to ProductEmbedding::DIMENSIONS
     */
    public function willReturn(array $vector): self
    {
        $this->queue[] = self::pad($vector);

        return $this;
    }

    public function willThrow(\Throwable $error): self
    {
        $this->queue[] = $error;

        return $this;
    }

    public function embedImage(string $mimeType, string $imageBytes): array
    {
        $this->calls[] = ['type' => 'image', 'prompt' => null, 'mimeType' => $mimeType, 'bytes' => $imageBytes];

        return $this->next($imageBytes);
    }

    public function embedQuery(string $text, string $mimeType, string $imageBytes): array
    {
        $this->calls[] = ['type' => 'query', 'prompt' => $text, 'mimeType' => $mimeType, 'bytes' => $imageBytes];

        return $this->next($text.$imageBytes);
    }

    public function model(): string
    {
        return self::MODEL;
    }

    /**
     * @return list<array{type: string, prompt: ?string, mimeType: string, bytes: string}>
     */
    public function calls(): array
    {
        return $this->calls;
    }

    public function callCount(): int
    {
        return \count($this->calls);
    }

    /**
     * @return list<float>
     */
    private function next(string $seed): array
    {
        $queued = array_shift($this->queue);

        if ($queued instanceof \Throwable) {
            throw $queued;
        }

        return $queued ?? self::deterministicVector($seed);
    }

    /**
     * @param list<float> $vector
     *
     * @return list<float>
     */
    public static function pad(array $vector): array
    {
        if (\count($vector) > ProductEmbedding::DIMENSIONS) {
            throw new \InvalidArgumentException(sprintf('A queued vector must not exceed %d dimensions, got %d.', ProductEmbedding::DIMENSIONS, \count($vector)));
        }

        return array_merge($vector, array_fill(0, ProductEmbedding::DIMENSIONS - \count($vector), 0.0));
    }

    /**
     * @return list<float>
     */
    private static function deterministicVector(string $seed): array
    {
        $vector = [];
        $block = hash('sha256', $seed, true);

        for ($i = 0; $i < ProductEmbedding::DIMENSIONS; ++$i) {
            if (0 === $i % 32) {
                $block = hash('sha256', $block, true);
            }

            $vector[] = (\ord($block[$i % 32]) - 127.5) / 127.5;
        }

        return $vector;
    }
}
