<?php

namespace App\Api\OpenApi;

use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;

#[AutoconfigureTag('app.api.openapi_tag_description_provider')]
interface TagDescriptionProviderInterface
{
    /**
     * @return array<string, string> tag name => description
     */
    public function getTagDescriptions(): array;
}
