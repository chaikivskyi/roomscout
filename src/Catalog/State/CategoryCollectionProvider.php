<?php

namespace App\Catalog\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\Catalog\ApiResource\CatalogCategory;
use App\Catalog\Entity\Category;
use App\Catalog\Service\CatalogCategoryMapper;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * @implements ProviderInterface<CatalogCategory>
 */
final class CategoryCollectionProvider implements ProviderInterface
{
    /**
     * @param ProviderInterface<object> $entities
     */
    public function __construct(
        #[Autowire(service: 'api_platform.doctrine.orm.state.collection_provider')]
        private readonly ProviderInterface $entities,
        private readonly CatalogCategoryMapper $mapper,
    ) {
    }

    /**
     * @return list<CatalogCategory>
     */
    public function provide(Operation $operation, array $uriVariables = [], array $context = []): array
    {
        $result = $this->entities->provide($operation, $uriVariables, $context);

        $items = [];

        if (is_iterable($result)) {
            foreach ($result as $category) {
                if ($category instanceof Category) {
                    $items[] = $this->mapper->map($category);
                }
            }
        }

        return $items;
    }
}
