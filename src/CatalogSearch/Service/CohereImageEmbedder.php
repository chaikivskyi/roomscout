<?php

namespace App\CatalogSearch\Service;

use App\CatalogSearch\Entity\ProductEmbedding;
use App\CatalogSearch\Exception\EmbeddingRateLimitedException;
use App\CatalogSearch\Exception\EmbeddingRejectedException;
use App\CatalogSearch\Exception\EmbeddingUnavailableException;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;

final class CohereImageEmbedder implements ImageEmbedderInterface
{
    private const MODEL = 'embed-v4.0';

    public function __construct(
        private readonly HttpClientInterface $cohereClient,
        private readonly EmbeddingImagePreprocessor $imagePreprocessor,
    ) {
    }

    public function embedImage(string $mimeType, string $imageBytes): array
    {
        return $this->embed('search_document', [
            $this->imageContent($mimeType, $imageBytes),
        ]);
    }

    public function embedQuery(string $text, string $mimeType, string $imageBytes): array
    {
        return $this->embed('search_query', [
            ['type' => 'text', 'text' => $text],
            $this->imageContent($mimeType, $imageBytes),
        ]);
    }

    public function model(): string
    {
        return self::MODEL;
    }

    /**
     * @param list<array<string, mixed>> $content
     *
     * @return list<float>
     */
    private function embed(string $inputType, array $content): array
    {
        $response = $this->cohereClient->request('POST', '/v2/embed', [
            'timeout' => 30,
            'max_duration' => 60,
            'json' => [
                'model' => self::MODEL,
                'input_type' => $inputType,
                'embedding_types' => ['float'],
                'output_dimension' => ProductEmbedding::DIMENSIONS,
                'inputs' => [['content' => $content]],
            ],
        ]);

        $status = $response->getStatusCode();

        if (429 === $status) {
            $retryAfter = $response->getHeaders(false)['retry-after'][0] ?? null;

            throw new EmbeddingRateLimitedException(
                'Cohere rate-limited the embed request.',
                null !== $retryAfter && ctype_digit($retryAfter) ? 1000 * (int) $retryAfter : null,
            );
        }
        if ($status >= 500) {
            throw new EmbeddingUnavailableException(sprintf('Cohere embed request failed with status %d: %s', $status, self::responseSummary($response)));
        }
        if ($status >= 400) {
            throw new EmbeddingRejectedException(sprintf('Cohere rejected the embed request with status %d: %s', $status, self::responseSummary($response)));
        }

        $vector = $response->toArray()['embeddings']['float'][0] ?? null;

        if (!\is_array($vector) || ProductEmbedding::DIMENSIONS !== \count($vector)) {
            throw new EmbeddingRejectedException('Unexpected Cohere embed response shape.');
        }

        return $vector;
    }

    private static function responseSummary(ResponseInterface $response): string
    {
        try {
            $body = trim($response->getContent(false));
        } catch (\Throwable) {
            return '(response body unavailable)';
        }

        return empty($body) ? '(empty response body)' : $body;
    }

    /**
     * @return array<string, mixed>
     */
    private function imageContent(string $mimeType, string $imageBytes): array
    {
        [$mimeType, $imageBytes] = $this->imagePreprocessor->prepare($mimeType, $imageBytes);

        return [
            'type' => 'image_url',
            'image_url' => ['url' => sprintf('data:%s;base64,%s', $mimeType, base64_encode($imageBytes))],
        ];
    }
}
