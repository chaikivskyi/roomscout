<?php

namespace App\CatalogScraper\Service;

use App\Catalog\Service\ProductThumbnailHasher;
use App\CatalogScraper\Dto\StoredThumbnail;
use App\CatalogScraper\Exception\ResponseTooLargeException;
use League\Flysystem\FilesystemOperator;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Mime\MimeTypesInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;

class ThumbnailDownloader
{
    private const ALLOWED_EXTENSIONS = ['jpg', 'jpeg', 'png', 'webp', 'gif'];

    private const MAX_BYTES = 10 * 1024 * 1024;

    public function __construct(
        private readonly HttpClientInterface $scraperClient,
        private readonly CappedResponseReader $responseReader,
        private readonly ProductThumbnailHasher $hasher,
        #[Autowire(service: 'product_thumbnails.storage')]
        private readonly FilesystemOperator $storage,
        private readonly MimeTypesInterface $mimeTypes,
        private readonly LoggerInterface $logger,
    ) {
    }

    public function store(string $sourceUrl, string $identifier): ?StoredThumbnail
    {
        try {
            $response = $this->fetch($sourceUrl);

            if (null === $response) {
                return null;
            }

            $contentType = $this->normalizeContentType($response->getHeaders(false)['content-type'][0] ?? '');
            $extension = $this->resolveExtension($contentType, $sourceUrl);

            if (null === $extension) {
                $this->logger->warning('Thumbnail {url} is not a supported image ({type}).', [
                    'url' => $sourceUrl,
                    'type' => $contentType,
                ]);

                return null;
            }

            try {
                $content = $this->responseReader->read($response, self::MAX_BYTES);
            } catch (ResponseTooLargeException) {
                $this->logger->warning('Thumbnail {url} exceeds the maximum size.', ['url' => $sourceUrl]);

                return null;
            }

            return $this->persist($identifier, $extension, $content);
        } catch (\Throwable $e) {
            $this->logger->warning('Failed to store thumbnail {url}: {message}', [
                'url' => $sourceUrl,
                'message' => $e->getMessage(),
            ]);

            return null;
        }
    }

    private function fetch(string $sourceUrl): ?ResponseInterface
    {
        $response = $this->scraperClient->request('GET', $sourceUrl, ['buffer' => false]);

        if (200 !== $response->getStatusCode()) {
            $this->logger->warning('Thumbnail {url} returned HTTP {status}.', [
                'url' => $sourceUrl,
                'status' => $response->getStatusCode(),
            ]);

            return null;
        }

        return $response;
    }

    private function persist(string $identifier, string $extension, string $content): StoredThumbnail
    {
        $path = sprintf('%s/thumbnail.%s', $identifier, $extension);

        $this->storage->write($path, $content);

        return new StoredThumbnail($path, $this->hasher->hashBytes($content));
    }

    private function normalizeContentType(string $header): string
    {
        return strtolower(trim(explode(';', $header)[0]));
    }

    private function resolveExtension(string $contentType, string $sourceUrl): ?string
    {
        $candidate = null;

        if (str_starts_with($contentType, 'image/')) {
            $candidate = $this->mimeTypes->getExtensions($contentType)[0] ?? null;
        }

        if (null === $candidate) {
            $urlExtension = strtolower(pathinfo((string) parse_url($sourceUrl, PHP_URL_PATH), PATHINFO_EXTENSION));
            $candidate = empty($urlExtension) ? null : $urlExtension;
        }

        return in_array($candidate, self::ALLOWED_EXTENSIONS, true) ? $candidate : null;
    }
}
