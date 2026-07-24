<?php

namespace App\CatalogSearch\OpenApi;

use App\Api\OpenApi\TagDescriptionProviderInterface;

final class CatalogSearchTagDescriptionProvider implements TagDescriptionProviderInterface
{
    public function getTagDescriptions(): array
    {
        return [
            'CatalogSearch / Matches' => 'Catalog products matched to a project\'s image + prompt query.',
        ];
    }
}
