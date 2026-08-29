<?php

namespace App\CatalogSearch\Command;

use App\Catalog\Entity\Product;
use App\CatalogSearch\Entity\ProductEmbedding;
use App\CatalogSearch\Exception\EmbeddingRateLimitedException;
use App\CatalogSearch\Exception\EmbeddingRejectedException;
use App\CatalogSearch\Repository\ProductEmbeddingRepository;
use App\CatalogSearch\Service\ImageEmbedderInterface;
use Doctrine\ORM\EntityManagerInterface;
use League\Flysystem\FilesystemOperator;
use Pgvector\Vector;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Messenger\Exception\RecoverableMessageHandlingException;
use Symfony\Component\Messenger\Exception\UnrecoverableMessageHandlingException;
use Symfony\Component\Uid\Uuid;

#[AsMessageHandler(bus: 'command.bus')]
final class EmbedProductThumbnailHandler
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly ProductEmbeddingRepository $embeddings,
        private readonly ImageEmbedderInterface $embedder,
        #[Autowire(service: 'product_thumbnails.storage')]
        private readonly FilesystemOperator $storage,
        private readonly LoggerInterface $logger,
    ) {
    }

    public function __invoke(EmbedProductThumbnail $command): void
    {
        $productId = $this->parseProductId($command->productId);
        $product = $this->entityManager->find(Product::class, $productId);

        if (null === $product) {
            throw new RecoverableMessageHandlingException(sprintf('Product %s not found.', $command->productId), forceRetry: false);
        }

        if ($this->embeddings->existsForProduct($productId)) {
            $this->logger->debug('Skipping embedding: product is already embedded.', ['productId' => $command->productId]);

            return;
        }

        $path = $product->getThumbnailUrl();

        if (null === $path || !$this->storage->fileExists($path)) {
            $this->logger->warning('Skipping embedding: thumbnail is missing from storage.', [
                'productId' => $command->productId,
                'path' => $path,
            ]);

            return;
        }

        $bytes = $this->storage->read($path);

        try {
            $vector = $this->embedder->embedImage($this->storage->mimeType($path), $bytes);
        } catch (EmbeddingRateLimitedException $e) {
            throw new RecoverableMessageHandlingException($e->getMessage(), previous: $e, retryDelay: $e->getRetryDelayMs(), forceRetry: false);
        } catch (EmbeddingRejectedException $e) {
            throw new UnrecoverableMessageHandlingException($e->getMessage(), previous: $e);
        }

        $this->embeddings->save(new ProductEmbedding(
            product: $product,
            embedding: new Vector($vector),
            model: $this->embedder->model(),
            sourceThumbnailHash: hash('sha256', $bytes),
            embeddedAt: new \DateTimeImmutable(),
        ));
    }

    private function parseProductId(string $productId): Uuid
    {
        try {
            return Uuid::fromString($productId);
        } catch (\InvalidArgumentException $e) {
            throw new UnrecoverableMessageHandlingException(sprintf('Malformed product id "%s".', $productId), previous: $e);
        }
    }
}
