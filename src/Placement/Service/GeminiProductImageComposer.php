<?php

namespace App\Placement\Service;

use App\Placement\Dto\ComposedImage;
use App\Placement\Exception\ImageGenerationRateLimitedException;
use App\Placement\Exception\ImageGenerationRejectedException;
use App\Placement\Exception\ImageGenerationUnavailableException;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;

final class GeminiProductImageComposer implements ProductImageComposerInterface
{
    private const INSTRUCTION = <<<'PROMPT'
        The first image is a photo of a room. The second image is a product photo.
        Edit the room photo by realistically placing the product into the room,
        matching the room's perspective, scale and lighting. Keep everything else
        in the room unchanged. User intent: %s
        Return only the edited room image.
        PROMPT;

    public function __construct(
        private readonly HttpClientInterface $geminiClient,
        private readonly GenerationImagePreprocessor $imagePreprocessor,
        #[Autowire('%env(PLACEMENT_IMAGE_MODEL)%')]
        private readonly string $model,
    ) {
    }

    public function compose(
        string $prompt,
        string $roomMimeType,
        string $roomBytes,
        string $productMimeType,
        string $productBytes,
    ): ComposedImage {
        $response = $this->geminiClient->request('POST', sprintf('/v1beta/models/%s:generateContent', $this->model), [
            'timeout' => 60,
            'max_duration' => 120,
            'json' => [
                'contents' => [[
                    'parts' => [
                        ['text' => sprintf(self::INSTRUCTION, $prompt)],
                        $this->imagePart($roomMimeType, $roomBytes),
                        $this->imagePart($productMimeType, $productBytes),
                    ],
                ]],
            ],
        ]);

        $status = $response->getStatusCode();

        if (429 === $status) {
            $retryAfter = $response->getHeaders(false)['retry-after'][0] ?? null;

            throw new ImageGenerationRateLimitedException('Gemini rate-limited the generation request.', null !== $retryAfter && ctype_digit($retryAfter) ? 1000 * (int) $retryAfter : null);
        }
        if ($status >= 500) {
            throw new ImageGenerationUnavailableException(sprintf('Gemini generation request failed with status %d: %s', $status, self::responseSummary($response)));
        }
        if ($status >= 400) {
            throw new ImageGenerationRejectedException(sprintf('Gemini rejected the generation request with status %d: %s', $status, self::responseSummary($response)));
        }

        /** @var array{candidates?: list<array{content?: array{parts?: list<array{inlineData?: array{mimeType?: string, data?: string}}>}}>} $payload */
        $payload = $response->toArray();

        foreach ($payload['candidates'][0]['content']['parts'] ?? [] as $part) {
            $mimeType = $part['inlineData']['mimeType'] ?? null;
            $data = $part['inlineData']['data'] ?? null;

            if (null === $mimeType || null === $data) {
                continue;
            }

            $bytes = base64_decode($data, true);

            if (false === $bytes || '' === $bytes) {
                throw new ImageGenerationRejectedException('Gemini returned an undecodable image payload.');
            }

            return new ComposedImage($mimeType, $bytes);
        }

        throw new ImageGenerationRejectedException(sprintf('Gemini response contained no image part: %s', self::responseSummary($response)));
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
    private function imagePart(string $mimeType, string $imageBytes): array
    {
        [$mimeType, $imageBytes] = $this->imagePreprocessor->prepare($mimeType, $imageBytes);

        return [
            'inlineData' => [
                'mimeType' => $mimeType,
                'data' => base64_encode($imageBytes),
            ],
        ];
    }
}
