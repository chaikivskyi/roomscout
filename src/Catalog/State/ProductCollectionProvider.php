<?php

namespace App\Catalog\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\Pagination\PaginatorInterface;
use ApiPlatform\State\Pagination\TraversablePaginator;
use ApiPlatform\State\ProviderInterface;
use App\Catalog\ApiResource\CatalogProduct;
use App\Catalog\Entity\Product;
use App\Catalog\Service\CatalogProductMapper;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * @implements ProviderInterface<CatalogProduct>
 */
final class ProductCollectionProvider implements ProviderInterface
{
    /**
     * @param ProviderInterface<object> $entities
     */
    public function __construct(
        #[Autowire(service: 'api_platform.doctrine.orm.state.collection_provider')]
        private readonly ProviderInterface $entities,
        private readonly CatalogProductMapper $mapper,
    ) {
    }

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): TraversablePaginator
    {
        $result = $this->entities->provide($operation, $uriVariables, $context);

        $items = [];

        if (is_iterable($result)) {
            foreach ($result as $product) {
                if ($product instanceof Product) {
                    $items[] = $this->mapper->map($product);
                }
            }
        }

        $page = $result instanceof PaginatorInterface ? (int) $result->getCurrentPage() : 1;
        $perPage = $result instanceof PaginatorInterface ? (int) $result->getItemsPerPage() : \count($items);
        $total = $result instanceof PaginatorInterface ? (int) $result->getTotalItems() : \count($items);

        return new TraversablePaginator(new \ArrayIterator($items), $page, $perPage, $total);
    }
}
