<?php

namespace App\CatalogScraper\Service;

use App\Catalog\Api\ProductInterface;
use App\CatalogScraper\Enum\ProductField;
use Symfony\Component\DomCrawler\Crawler;
use Symfony\Component\DomCrawler\UriResolver;

class ProductMapper
{
    public function __construct(
        private readonly FieldValueCaster $caster,
    ) {
    }

    /**
     * @param list<array{field: string, selector: string, attribute: ?string}> $mappings
     */
    public function mapInto(ProductInterface $product, Crawler $page, array $mappings): void
    {
        foreach ($mappings as $mapping) {
            $field = ProductField::tryFrom($mapping['field']);

            if (null === $field) {
                continue;
            }

            $raw = $this->extract($page, $mapping['selector'], $mapping['attribute'] ?? null);

            if (null === $raw) {
                continue;
            }

            $value = $this->caster->cast($field, $raw);

            if (null === $value) {
                continue;
            }

            if (ProductField::ThumbnailUrl === $field) {
                $value = UriResolver::resolve($value, $page->getUri() ?? '');
            }

            $this->assign($product, $field, $value);
        }
    }

    private function extract(Crawler $page, string $selector, ?string $attribute): ?string
    {
        $node = $page->filter($selector);

        if (0 === $node->count()) {
            return null;
        }

        $node = $node->first();

        return $attribute
            ? $node->attr($attribute)
            : $node->text('');
    }

    private function assign(ProductInterface $product, ProductField $field, string|float $value): void
    {
        match ($field) {
            ProductField::Title => $product->setTitle($value),
            ProductField::Description => $product->setDescription($value),
            ProductField::ThumbnailUrl => $product->setThumbnailUrl($value),
            ProductField::ExternalId => $product->setExternalId($value),
            ProductField::Price => $product->setPrice($value),
            ProductField::WidthSm => $product->setWidthSm($value),
            ProductField::HeightSm => $product->setHeightSm($value),
            ProductField::DepthSm => $product->setDepthSm($value),
        };
    }
}
