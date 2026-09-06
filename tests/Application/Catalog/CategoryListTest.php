<?php

namespace App\Tests\Application\Catalog;

use ApiPlatform\Symfony\Bundle\Test\Client;
use App\Tests\Application\ApiTestCase;
use App\Tests\Factory\CategoryFactory;
use Doctrine\Bundle\DoctrineBundle\DataCollector\DoctrineDataCollector;
use Symfony\Component\HttpKernel\Profiler\Profile;
use Symfony\Component\Uid\Uuid;
use Symfony\Contracts\HttpClient\ResponseInterface;

final class CategoryListTest extends ApiTestCase
{
    private const URL = '/api/catalog/categories';

    public function testListsRootCategoriesAlphabeticallyWithIconAndNoParent(): void
    {
        $lighting = CategoryFactory::createOne(['title' => 'Lighting', 'iconUrl' => 'icons/lighting.png']);
        $seating = CategoryFactory::createOne(['title' => 'Seating']);
        CategoryFactory::createOne(['title' => 'Coffee tables', 'parent' => $seating]);

        $data = self::decode(static::createClient()->request('GET', self::URL));

        self::assertResponseIsSuccessful();
        self::assertSame(2, $data['totalItems']);
        self::assertSame(['Lighting', 'Seating'], array_column($data['member'], 'title'));

        $first = $data['member'][0];
        self::assertSame($lighting->getId()->toRfc4122(), $first['id']);
        self::assertSame('http://localhost/uploads/category/icons/lighting.png', $first['iconUrl']);
        self::assertNull($first['parentId']);
        self::assertNull($data['member'][1]['iconUrl'], 'A category without an icon must report null, not a URL to nothing.');

        self::assertEqualsCanonicalizing(
            ['@id', '@type', 'id', 'title', 'iconUrl', 'parentId'],
            array_keys($first),
            'The category payload must expose exactly these fields — no Category internals.',
        );
    }

    public function testEmptyCatalogYieldsAnEmptyCollection(): void
    {
        $data = self::decode(static::createClient()->request('GET', self::URL));

        self::assertResponseIsSuccessful();
        self::assertSame(0, $data['totalItems']);
        self::assertSame([], $data['member']);
    }

    public function testIsReachableWithoutAuthentication(): void
    {
        CategoryFactory::createOne();

        static::createClient()->request('GET', self::URL);

        self::assertResponseIsSuccessful();
    }

    public function testGeneratedItemIriRouteStaysBehindTheFirewall(): void
    {
        static::createClient()->request('GET', '/api/catalog_categories/'.Uuid::v7()->toRfc4122());

        self::assertResponseStatusCodeSame(401, 'Publishing the collection must not expose the IRI route API Platform generates alongside it.');
    }

    public function testParentParameterReturnsDirectChildrenNamingTheirParent(): void
    {
        $seating = CategoryFactory::createOne(['title' => 'Seating']);
        $sofas = CategoryFactory::createOne(['title' => 'Sofas', 'parent' => $seating]);
        $armchairs = CategoryFactory::createOne(['title' => 'Armchairs', 'parent' => $seating]);
        CategoryFactory::createOne(['title' => 'Recliners', 'parent' => $armchairs]);
        CategoryFactory::createOne(['title' => 'Lighting']);

        $data = self::decode(static::createClient()
            ->request('GET', self::URL.'?parent='.$seating->getId()->toRfc4122()));

        self::assertResponseIsSuccessful();
        self::assertSame(
            ['Armchairs', 'Sofas'],
            array_column($data['member'], 'title'),
            'Only direct children, alphabetically — grandchildren and unrelated roots must not appear.',
        );
        self::assertSame(
            [$armchairs->getId()->toRfc4122(), $sofas->getId()->toRfc4122()],
            array_column($data['member'], 'id'),
        );

        self::assertSame(
            [$seating->getId()->toRfc4122(), $seating->getId()->toRfc4122()],
            array_column($data['member'], 'parentId'),
            'Each child must name its parent, so one request answers both directions of the tree.',
        );
    }

    public function testLeafCategoryYieldsAnEmptyCollection(): void
    {
        $leaf = CategoryFactory::createOne(['title' => 'Lighting']);
        CategoryFactory::createOne(['title' => 'Seating']);

        $data = self::decode(static::createClient()
            ->request('GET', self::URL.'?parent='.$leaf->getId()->toRfc4122()));

        self::assertResponseIsSuccessful();
        self::assertSame(0, $data['totalItems']);
        self::assertSame([], $data['member']);
    }

    public function testUnknownParentYieldsAnEmptyCollectionRatherThanTheRoots(): void
    {
        CategoryFactory::createMany(3);

        $data = self::decode(static::createClient()
            ->request('GET', self::URL.'?parent='.Uuid::v7()->toRfc4122()));

        self::assertResponseIsSuccessful();
        self::assertSame(0, $data['totalItems']);
        self::assertSame([], $data['member']);
    }

    public function testMalformedParentParameterIsRejected(): void
    {
        $client = static::createClient();

        $client->request('GET', self::URL.'?parent=abc');
        self::assertResponseStatusCodeSame(422);

        $client->request('GET', self::URL.'?parent=0');
        self::assertResponseStatusCodeSame(422);
    }

    public function testAnEmptyParentParameterFallsBackToTheRoots(): void
    {
        $seating = CategoryFactory::createOne(['title' => 'Seating']);
        CategoryFactory::createOne(['title' => 'Sofas', 'parent' => $seating]);

        $data = self::decode(static::createClient()->request('GET', self::URL.'?parent='));

        self::assertResponseIsSuccessful();
        self::assertSame(
            ['Seating'],
            array_column($data['member'], 'title'),
            'An empty parent reads as no parent at all, so it must serve the roots rather than 422 or an empty list.',
        );
    }

    public function testListingOneLevelCostsASingleQueryHoweverManySiblingsItHas(): void
    {
        $seating = CategoryFactory::createOne(['title' => 'Seating']);

        foreach (['Armchairs', 'Benches', 'Ottomans', 'Sofas', 'Stools'] as $title) {
            CategoryFactory::createOne(['title' => $title, 'parent' => $seating]);
        }

        $this->entityManager()->clear();

        $client = static::createClient();
        $client->getKernelBrowser()->enableProfiler();

        $data = self::decode($client->request('GET', self::URL.'?parent='.$seating->getId()->toRfc4122()));

        self::assertResponseIsSuccessful();
        self::assertCount(5, $data['member']);

        self::assertCount(
            1,
            self::categorySelects($client),
            'One SELECT must serve the whole level. CatalogCategoryMapper reads only $parent->getId(), which Doctrine pre-populates on the lazy proxy, so the parent is never initialised. Anything that touches the parent further — Category::__toString() returns the title — loads it, costing a query per distinct parent, and the join dropped from findChildrenOf() has to come back.',
        );
    }

    /**
     * @return list<string>
     */
    private static function categorySelects(Client $client): array
    {
        $profile = $client->getKernelBrowser()->getProfile();
        self::assertInstanceOf(Profile::class, $profile, 'Enable the profiler before the request, or this assertion proves nothing.');

        $collector = $profile->getCollector('db');
        self::assertInstanceOf(DoctrineDataCollector::class, $collector);

        /** @var array<string, list<array{sql: string}>> $byConnection */
        $byConnection = $collector->getQueries();

        $selects = [];

        foreach ($byConnection as $queries) {
            foreach ($queries as $query) {
                $sql = $query['sql'];

                if (str_starts_with(ltrim($sql), 'SELECT') && str_contains($sql, 'category')) {
                    $selects[] = $sql;
                }
            }
        }

        return $selects;
    }

    /**
     * @return array{totalItems: int, member: list<array{id: string, title: string, iconUrl: string|null, parentId: string|null}>}
     */
    private static function decode(ResponseInterface $response): array
    {
        /** @var array{totalItems: int, member: list<array{id: string, title: string, iconUrl: string|null, parentId: string|null}>} $data */
        $data = $response->toArray();

        return $data;
    }
}
