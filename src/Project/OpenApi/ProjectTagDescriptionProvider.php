<?php

namespace App\Project\OpenApi;

use App\Api\OpenApi\TagDescriptionProviderInterface;

final class ProjectTagDescriptionProvider implements TagDescriptionProviderInterface
{
    public function getTagDescriptions(): array
    {
        return [
            'Project / Projects' => 'Image + prompt search queries against the product catalog.',
            'Project / Contexts' => 'Prompt variations of a project; each context is matched against the catalog independently.',
        ];
    }
}
