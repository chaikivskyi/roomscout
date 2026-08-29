<?php

namespace App\CatalogScraper\Service;

use App\Catalog\Entity\Product;
use App\Catalog\Repository\ProductRepository;
use App\CatalogScraper\Entity\ScrapeSource;
use Psr\Log\LoggerInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;

class ScraperService
{
    public function __construct(
        private readonly ProductRepository $products,
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
            $managed = null;

            try {
                $page = $this->pageFetcher->fetch($productUrl);

                $scraped = new Product();
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
                $managed = $existing;
                $isNew = null === $existing;
                $product = $existing ?? $scraped;

                if ($isNew) {
                    $product->setUrl($productUrl);
                    $product->setCategory($category);
                } else {
                    if (null !== $scraped->getPrice()) {
                        $product->setPrice($scraped->getPrice());
                    }

                    if (!$this->isValid($product, $productUrl)) {
                        ++$skipped;
                        $this->products->discardChanges($product);
                        continue;
                    }
                }

                $remoteThumbnail = $scraped->getThumbnailUrl();
                $storedThumbnail = null !== $remoteThumbnail
                    ? $this->thumbnailDownloader->store($remoteThumbnail, (string) $product->getId())
                    : null;

                if (null !== $storedThumbnail) {
                    $product->setThumbnailUrl($storedThumbnail->path);
                    $product->setThumbnailHash($storedThumbnail->hash);
                } elseif ($isNew) {
                    ++$skipped;
                    $this->logger->warning('Skipping product {url}: thumbnail could not be stored.', [
                        'url' => $productUrl,
                    ]);
                    continue;
                }

                if ($isNew && !$this->isValid($product, $productUrl)) {
                    ++$skipped;
                    continue;
                }

                $this->products->save($product);

                $isNew ? ++$created : ++$updated;
            } catch (\Throwable $e) {
                ++$skipped;

                if (null !== $managed) {
                    $this->products->discardChanges($managed);
                }

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

    private function isValid(Product $product, string $productUrl): bool
    {
        $errors = $this->validator->validate($product);

        if (0 === count($errors)) {
            return true;
        }

        $this->logger->warning('Skipping invalid product {url}: {errors}', [
            'url' => $productUrl,
            'errors' => (string) $errors,
        ]);

        return false;
    }
}
