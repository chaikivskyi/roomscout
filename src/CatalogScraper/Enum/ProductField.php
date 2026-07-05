<?php

namespace App\CatalogScraper\Enum;

/**
 * Product fields that a scrape mapping can populate. `url` and `category` are
 * intentionally excluded: the product URL is the crawled detail-page URL and the
 * category is taken from the ScrapeSource.
 */
enum ProductField: string
{
    case Title = 'title';
    case Description = 'description';
    case ThumbnailUrl = 'thumbnail_url';
    case Price = 'price';
    case WidthSm = 'width_sm';
    case HeightSm = 'height_sm';
    case DepthSm = 'depth_sm';
    case ExternalId = 'external_id';

    public function label(): string
    {
        return match ($this) {
            self::Title => 'Title',
            self::Description => 'Description',
            self::ThumbnailUrl => 'Thumbnail URL',
            self::Price => 'Price',
            self::WidthSm => 'Width (cm)',
            self::HeightSm => 'Height (cm)',
            self::DepthSm => 'Depth (cm)',
            self::ExternalId => 'External ID',
        };
    }

    /**
     * How a raw scraped string should be cast before it is stored on the Product.
     */
    public function castType(): string
    {
        return match ($this) {
            self::Price, self::WidthSm, self::HeightSm, self::DepthSm => 'float',
            default => 'string',
        };
    }

    /**
     * @return array<string, string>
     */
    public static function choices(): array
    {
        $choices = [];

        foreach (self::cases() as $case) {
            $choices[$case->label()] = $case->value;
        }

        return $choices;
    }

    /**
     * Backing values, e.g. for an Assert\Choice constraint.
     *
     * @return list<string>
     */
    public static function values(): array
    {
        return array_map(static fn (self $case): string => $case->value, self::cases());
    }
}
