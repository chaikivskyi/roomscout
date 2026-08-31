<?php

namespace App\Tests\Application\Catalog;

use App\Tests\Application\ApiTestCase;
use App\Tests\Factory\CategoryFactory;
use App\Tests\Factory\ProductFactory;
use Symfony\Component\Uid\Uuid;
use Symfony\Contracts\HttpClient\ResponseInterface;

final class ProductListTest extends ApiTestCase
{
    private const URL = '/api/catalog/products';

    public function testListsProductsNewestFirstWithCatalogDetails(): void
    {
        $category = CategoryFactory::createOne(['title' => 'Coffee tables']);
        ProductFactory::createOne(['title' => 'Oldest']);
        ProductFactory::createOne(['title' => 'Middle']);
        $newest = ProductFactory::createOne([
            'title' => 'Walnut coffee table',
            'description' => 'Mid-century walnut coffee table.',
            'url' => 'https://shop.example.com/walnut-coffee-table',
            'thumbnailUrl' => 'abc/thumbnail.jpg',
            'price' => 249.5,
            'category' => $category,
        ]);

        $response = static::createClient()->request('GET', self::URL);

        self::assertResponseIsSuccessful();

        $data = self::decode($response);
        self::assertSame(3, $data['totalItems']);
        self::assertSame(['Walnut coffee table', 'Middle', 'Oldest'], array_column($data['member'], 'title'));

        $first = $data['member'][0];
        self::assertSame($newest->getId()->toRfc4122(), $first['id']);
        self::assertSame(249.5, $first['price']);
        self::assertSame('http://localhost/uploads/product/abc/thumbnail.jpg', $first['imageUrl']);
        self::assertSame('https://shop.example.com/walnut-coffee-table', $first['url']);
        self::assertSame(
            ['id' => $category->getId()->toRfc4122(), 'title' => 'Coffee tables'],
            ['id' => $first['category']['id'], 'title' => $first['category']['title']],
        );

        self::assertEqualsCanonicalizing(
            ['@id', '@type', 'id', 'title', 'price', 'imageUrl', 'url', 'category'],
            array_keys($first),
            'The product payload must expose exactly these fields — no Product internals.',
        );
    }

    public function testIsReachableWithoutAuthentication(): void
    {
        ProductFactory::createOne();

        static::createClient()->request('GET', self::URL);

        self::assertResponseIsSuccessful();
    }

    public function testGeneratedItemIriRouteStaysBehindTheFirewall(): void
    {
        static::createClient()->request('GET', '/api/catalog_products/'.Uuid::v7()->toRfc4122());

        self::assertResponseStatusCodeSame(401, 'Publishing the collection must not expose the IRI route API Platform generates alongside it.');
    }

    public function testZeroPriceBoundIsAcceptedAndStillExcludesUnpricedProducts(): void
    {
        ProductFactory::createOne(['price' => 0.0]);
        ProductFactory::createOne(['price' => 40.0]);
        ProductFactory::createOne(['price' => null]);

        $data = self::decode(static::createClient()->request('GET', self::URL.'?priceMin=0'));

        self::assertResponseIsSuccessful();
        self::assertSame([40.0, 0.0], self::memberPrices($data));
    }

    public function testEmptyCatalogAndPagesBeyondTheLastYieldEmptyCollections(): void
    {
        $client = static::createClient();

        $data = self::decode($client->request('GET', self::URL));
        self::assertResponseIsSuccessful();
        self::assertSame(0, $data['totalItems']);
        self::assertSame([], $data['member']);

        ProductFactory::createOne();

        $data = self::decode($client->request('GET', self::URL.'?page=99'));
        self::assertResponseIsSuccessful();
        self::assertSame(1, $data['totalItems']);
        self::assertSame([], $data['member']);
    }

    public function testPriceBoundsFilterAndExcludeUnpricedProducts(): void
    {
        ProductFactory::createOne(['price' => 10.0]);
        ProductFactory::createOne(['price' => 50.0]);
        ProductFactory::createOne(['price' => null]);

        $client = static::createClient();

        $data = self::decode($client->request('GET', self::URL.'?priceMin=20'));
        self::assertSame([50.0], self::memberPrices($data));

        $data = self::decode($client->request('GET', self::URL.'?priceMax=20'));
        self::assertSame([10.0], self::memberPrices($data));

        $data = self::decode($client->request('GET', self::URL.'?priceMin=5&priceMax=60'));
        self::assertSame(2, $data['totalItems']);
    }

    public function testCategoryFilterIncludesDescendantCategories(): void
    {
        $parent = CategoryFactory::createOne(['title' => 'Tables']);
        $child = CategoryFactory::createOne(['title' => 'Coffee tables', 'parent' => $parent]);
        $other = CategoryFactory::createOne(['title' => 'Lighting']);

        $inParent = ProductFactory::createOne(['category' => $parent]);
        $inChild = ProductFactory::createOne(['category' => $child]);
        ProductFactory::createOne(['category' => $other]);

        $client = static::createClient();

        $data = self::decode($client->request('GET', self::URL.'?category='.$parent->getId()));
        self::assertSame(2, $data['totalItems']);
        self::assertEqualsCanonicalizing(
            [$inParent->getId()->toRfc4122(), $inChild->getId()->toRfc4122()],
            array_column($data['member'], 'id'),
        );

        $data = self::decode($client->request('GET', self::URL.'?category='.$child->getId()));
        self::assertSame([$inChild->getId()->toRfc4122()], array_column($data['member'], 'id'));
    }

    public function testUnknownCategoryYieldsAnEmptyPageRatherThanTheWholeCatalog(): void
    {
        ProductFactory::createMany(3);

        $data = self::decode(static::createClient()
            ->request('GET', self::URL.'?category='.Uuid::v7()->toRfc4122()));

        self::assertResponseIsSuccessful();
        self::assertSame(0, $data['totalItems']);
        self::assertSame([], $data['member']);
    }

    public function testCombinedCategoryAndPriceFiltersIntersect(): void
    {
        $tables = CategoryFactory::createOne(['title' => 'Tables']);
        $lighting = CategoryFactory::createOne(['title' => 'Lighting']);

        ProductFactory::createOne(['category' => $tables, 'price' => 10.0]);
        $priceyTable = ProductFactory::createOne(['category' => $tables, 'price' => 100.0]);
        ProductFactory::createOne(['category' => $lighting, 'price' => 80.0]);

        $data = self::decode(static::createClient()
            ->request('GET', self::URL.'?category='.$tables->getId().'&priceMin=50'));

        self::assertSame(1, $data['totalItems']);
        self::assertSame($priceyTable->getId()->toRfc4122(), $data['member'][0]['id']);
    }

    public function testPaginatesWithFifteenItemsPerPage(): void
    {
        ProductFactory::createMany(20);

        $client = static::createClient();

        $data = self::decode($client->request('GET', self::URL));
        self::assertSame(20, $data['totalItems']);
        self::assertCount(15, $data['member']);

        $firstPageIds = array_column($data['member'], 'id');

        $data = self::decode($client->request('GET', self::URL.'?page=2'));
        self::assertCount(5, $data['member']);

        $secondPageIds = array_column($data['member'], 'id');
        self::assertSame([], array_intersect($firstPageIds, $secondPageIds), 'Pages must not overlap.');
        self::assertLessThan(
            end($firstPageIds),
            $secondPageIds[0],
            'Page 2 must continue the descending id order, so no row is skipped or repeated.',
        );
    }

    public function testInvalidQueryParametersAreRejected(): void
    {
        $client = static::createClient();

        $client->request('GET', self::URL.'?priceMin=-1');
        self::assertResponseStatusCodeSame(422);

        $client->request('GET', self::URL.'?priceMax=-1');
        self::assertResponseStatusCodeSame(422);

        $client->request('GET', self::URL.'?priceMin=abc');
        self::assertResponseStatusCodeSame(422);

        $client->request('GET', self::URL.'?priceMin=10.5');
        self::assertResponseStatusCodeSame(422);

        $client->request('GET', self::URL.'?priceMax=10.5');
        self::assertResponseStatusCodeSame(422);

        $client->request('GET', self::URL.'?category=abc');
        self::assertResponseStatusCodeSame(422);

        $client->request('GET', self::URL.'?category=0');
        self::assertResponseStatusCodeSame(422);

        $client->request('GET', self::URL.'?priceMin=30&priceMax=10');
        self::assertResponseStatusCodeSame(422);
    }

    /**
     * @param array{member: list<array{price: float|null}>} $data
     *
     * @return list<float|null>
     */
    private static function memberPrices(array $data): array
    {
        return array_map(
            static fn (array $member) => null === $member['price'] ? null : (float) $member['price'],
            $data['member'],
        );
    }

    /**
     * @return array{totalItems: int, member: list<array{id: string, title: string, price: float|null, imageUrl: string, url: string, category: array{id: string, title: string}}>}
     */
    private static function decode(ResponseInterface $response): array
    {
        /** @var array{totalItems: int, member: list<array{id: string, title: string, price: float|null, imageUrl: string, url: string, category: array{id: string, title: string}}>} $data */
        $data = $response->toArray();

        return $data;
    }
}
