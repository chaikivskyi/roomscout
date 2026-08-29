<?php

namespace App\CatalogSearch\Service;

use App\CatalogSearch\ApiResource\ProjectMatch;
use App\CatalogSearch\Entity\ProjectProductMatch;
use League\Flysystem\FilesystemOperator;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

final class ProjectMatchMapper
{
    public function __construct(
        #[Autowire(service: 'product_thumbnails.storage')]
        private readonly FilesystemOperator $thumbnailStorage,
    ) {
    }

    public function map(ProjectProductMatch $match): ProjectMatch
    {
        $product = $match->getProduct();

        return new ProjectMatch(
            id: (string) $product->getId(),
            title: (string) $product->getTitle(),
            price: $product->getPrice(),
            imageUrl: $this->thumbnailStorage->publicUrl((string) $product->getThumbnailUrl()),
            score: $match->getMatchScore(),
            url: (string) $product->getUrl(),
        );
    }
}
