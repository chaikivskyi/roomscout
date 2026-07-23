<?php

namespace App\CatalogSearch\MessageHandler;

use App\Catalog\Entity\Product;
use App\CatalogSearch\Entity\ProductEmbedding;
use App\CatalogSearch\Message\EmbedProductThumbnailMessage;
use App\CatalogSearch\Repository\ProductEmbeddingRepository;
use App\CatalogSearch\Service\ImageEmbedderInterface;
use Doctrine\ORM\EntityManagerInterface;
use League\Flysystem\FilesystemOperator;
use Pgvector\Vector;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Messenger\Exception\RecoverableMessageHandlingException;

#[AsMessageHandler]
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

    public function __invoke(EmbedProductThumbnailMessage $message): void
    {
        $product = $this->entityManager->find(Product::class, $message->productId);

        if (null === $product) {
            throw new RecoverableMessageHandlingException(sprintf('Product %d not found.', $message->productId), forceRetry: false);
        }

        if ($this->embeddings->existsForProduct($message->productId)) {
            $this->logger->debug('Skipping embedding: product is already embedded.', ['productId' => $message->productId]);

            return;
        }

        $path = $product->getThumbnailUrl();

        if (null === $path || !$this->storage->fileExists($path)) {
            $this->logger->warning('Skipping embedding: thumbnail is missing from storage.', [
                'productId' => $message->productId,
                'path' => $path,
            ]);

            return;
        }

        $bytes = $this->storage->read($path);
        $vector = $this->embedder->embedImage($this->storage->mimeType($path), $bytes);

        $this->embeddings->save(new ProductEmbedding(
            product: $product,
            embedding: new Vector($vector),
            model: $this->embedder->model(),
            sourceThumbnailHash: hash('sha256', $bytes),
            embeddedAt: new \DateTimeImmutable(),
        ));
    }
}
