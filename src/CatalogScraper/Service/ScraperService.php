<?php

namespace App\CatalogScraper\Service;

use App\Catalog\Api\ProductRepositoryInterface;
use App\CatalogScraper\Entity\ScrapeSource;
use Psr\Log\LoggerInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;

class ScraperService
{
    public function __construct(
        private readonly ProductRepositoryInterface $products,
        private readonly ProductUrlCollector $productUrlCollector,
        private readonly PageFetcher $pageFetcher,
        private readonly ProductMapper $productMapper,
        private readonly ThumbnailDownloader $thumbnailDownloader,
        private readonly ValidatorInterface $validator,
        private readonly LoggerInterface $logger,
    ) {
    }

    public function scrape(ScrapeSource $source): void
    {
        $this->logger->info('Scraping "{title}" ({url}).', [
            'title' => $source->getTitle(),
            'url' => $source->getSourceUrl(),
        ]);

        $category = $source->getCategory();
        $created = 0;
        $updated = 0;
        $skipped = 0;

        foreach ($this->productUrlCollector->collect($source) as $productUrl) {
            try {
                $page = $this->pageFetcher->fetch($productUrl);

                $scraped = $this->products->create();
                $this->productMapper->mapInto($scraped, $page, $source->getMappings());

                $externalId = $scraped->getExternalId();

                if (empty($externalId)) {
                    ++$skipped;
                    $this->logger->warning('Skipping product {url}: no external id scraped.', [
                        'url' => $productUrl,
                    ]);
                    continue;
                }

                $existing = $this->products->findOneByExternalId($externalId);
                $isNew = null === $existing;
                $product = $existing ?? $scraped;
                $remoteThumbnail = $scraped->getThumbnailUrl();
                $storedThumbnail = null !== $remoteThumbnail
                    ? $this->thumbnailDownloader->store($remoteThumbnail, (string) $product->getUuid())
                    : null;

                if ($isNew) {
                    if (null === $storedThumbnail) {
                        ++$skipped;
                        $this->logger->warning('Skipping product {url}: thumbnail could not be stored.', [
                            'url' => $productUrl,
                        ]);
                        continue;
                    }

                    $product->setThumbnailUrl($storedThumbnail);
                    $product->setUrl($productUrl);
                    $product->setCategory($category);
                } else {
                    if (null !== $scraped->getPrice()) {
                        $product->setPrice($scraped->getPrice());
                    }

                    if (null !== $storedThumbnail) {
                        $product->setThumbnailUrl($storedThumbnail);
                    }
                }

                $errors = $this->validator->validate($product);

                if (count($errors) > 0) {
                    ++$skipped;
                    $this->logger->warning('Skipping invalid product {url}: {errors}', [
                        'url' => $productUrl,
                        'errors' => (string) $errors,
                    ]);
                    continue;
                }

                $this->products->save($product);

                $isNew ? ++$created : ++$updated;
            } catch (\Throwable $e) {
                ++$skipped;
                $this->logger->error('Failed to scrape product {url}: {message}', [
                    'url' => $productUrl,
                    'message' => $e->getMessage(),
                ]);
            }
        }

        $this->logger->info('Finished "{title}": {created} created, {updated} updated, {skipped} skipped.', [
            'title' => $source->getTitle(),
            'created' => $created,
            'updated' => $updated,
            'skipped' => $skipped,
        ]);
    }
}
