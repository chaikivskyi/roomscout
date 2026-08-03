<?php

namespace App\Placement\OpenApi;

use App\Api\OpenApi\TagDescriptionProviderInterface;

final class PlacementTagDescriptionProvider implements TagDescriptionProviderInterface
{
    public function getTagDescriptions(): array
    {
        return [
            'Placement / Placements' => 'AI-generated images that render a matched product into the project photo.',
        ];
    }
}
