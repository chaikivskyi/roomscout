<?php

namespace App\Catalog\OpenApi;

use App\Api\OpenApi\TagDescriptionProviderInterface;

final class CatalogTagDescriptionProvider implements TagDescriptionProviderInterface
{
    public function getTagDescriptions(): array
    {
        return [
            'Catalog / Categories' => 'Public browsing of the catalog category tree.',
            'Catalog / Products' => 'Public browsing of the product catalog.',
        ];
    }
}
