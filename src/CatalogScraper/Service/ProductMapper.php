<?php

namespace App\CatalogScraper\Service;

use App\Catalog\Entity\Product;
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
    public function mapInto(Product $product, Crawler $page, array $mappings): void
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
                $value = UriResolver::resolve((string) $value, $page->getUri() ?? '');
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

    private function assign(Product $product, ProductField $field, string|float $value): void
    {
        match ($field) {
            ProductField::Title => $product->setTitle((string) $value),
            ProductField::Description => $product->setDescription((string) $value),
            ProductField::ThumbnailUrl => $product->setThumbnailUrl((string) $value),
            ProductField::ExternalId => $product->setExternalId((string) $value),
            ProductField::Price => $product->setPrice((float) $value),
            ProductField::WidthSm => $product->setWidthSm((float) $value),
            ProductField::HeightSm => $product->setHeightSm((float) $value),
            ProductField::DepthSm => $product->setDepthSm((float) $value),
        };
    }
}
