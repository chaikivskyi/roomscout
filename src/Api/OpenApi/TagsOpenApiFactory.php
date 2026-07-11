<?php

namespace App\Api\OpenApi;

use ApiPlatform\OpenApi\Factory\OpenApiFactoryInterface;
use ApiPlatform\OpenApi\Model\Tag;
use ApiPlatform\OpenApi\OpenApi;
use Symfony\Component\DependencyInjection\Attribute\AsDecorator;
use Symfony\Component\DependencyInjection\Attribute\AutowireIterator;

#[AsDecorator('api_platform.openapi.factory', priority: -100)]
final class TagsOpenApiFactory implements OpenApiFactoryInterface
{
    public function __construct(
        private readonly OpenApiFactoryInterface $decorated,
        /** @var iterable<TagDescriptionProviderInterface> */
        #[AutowireIterator('app.api.openapi_tag_description_provider')]
        private readonly iterable $descriptionProviders,
    ) {
    }

    public function __invoke(array $context = []): OpenApi
    {
        $openApi = $this->decorated->__invoke($context);

        $descriptions = [];
        foreach ($this->descriptionProviders as $provider) {
            $descriptions = [...$descriptions, ...$provider->getTagDescriptions()];
        }

        $names = [];
        foreach ($openApi->getPaths()->getPaths() as $pathItem) {
            foreach ([$pathItem->getGet(), $pathItem->getPost(), $pathItem->getPut(), $pathItem->getPatch(), $pathItem->getDelete()] as $operation) {
                foreach ($operation?->getTags() ?? [] as $name) {
                    $names[$name] = true;
                }
            }
        }

        $names = array_keys($names);
        sort($names);

        return $openApi->withTags(array_map(
            static fn (string $name): Tag => new Tag($name, $descriptions[$name] ?? null),
            $names,
        ));
    }
}
