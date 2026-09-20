<?php

namespace App\Visualization\OpenApi;

use App\Api\OpenApi\TagDescriptionProviderInterface;

final class VisualizationTagDescriptionProvider implements TagDescriptionProviderInterface
{
    public function getTagDescriptions(): array
    {
        return [
            'Visualization / Visualizations' => 'AI-generated images that render a matched product into the project photo.',
        ];
    }
}
