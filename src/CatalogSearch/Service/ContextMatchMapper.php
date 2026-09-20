<?php

namespace App\CatalogSearch\Service;

use App\CatalogSearch\ApiResource\ContextMatch;
use App\CatalogSearch\Entity\ProjectProductMatch;
use League\Flysystem\FilesystemOperator;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

final class ContextMatchMapper
{
    public function __construct(
        #[Autowire(service: 'product_thumbnails.storage')]
        private readonly FilesystemOperator $thumbnailStorage,
    ) {
    }

    public function map(ProjectProductMatch $match): ContextMatch
    {
        $product = $match->getProduct();

        return new ContextMatch(
            id: (string) $product->getId(),
            title: (string) $product->getTitle(),
            price: $product->getPrice(),
            imageUrl: $this->thumbnailStorage->publicUrl((string) $product->getThumbnailUrl()),
            score: $match->getMatchScore(),
            url: (string) $product->getUrl(),
        );
    }
}
