<?php

namespace App\Tests\Application\CatalogSearch;

use App\Catalog\Entity\Category;
use App\Identity\Entity\User;
use App\Project\Entity\ProjectContext;
use App\Tests\Application\ApiTestCase;
use App\Tests\Factory\CategoryFactory;
use App\Tests\Factory\ProductFactory;
use App\Tests\Factory\ProjectContextFactory;
use App\Tests\Factory\ProjectFactory;
use App\Tests\Factory\ProjectProductMatchFactory;
use App\Tests\Factory\UserFactory;
use Symfony\Component\Uid\Uuid;
use Symfony\Contracts\HttpClient\ResponseInterface;

final class ProjectMatchFiltersTest extends ApiTestCase
{
    public function testReturnsCategoryCountsAndPriceBounds(): void
    {
        $user = UserFactory::createOne();
        $context = $this->contextFor($user);
        $tables = CategoryFactory::createOne(['title' => 'Tables']);
        $lighting = CategoryFactory::createOne(['title' => 'Lighting']);
        $rugs = CategoryFactory::createOne(['title' => 'Rugs']);

        $this->match($context, $tables, 249.5);
        $this->match($context, $tables, 100.0);
        $this->match($context, $lighting, 79.9);
        $this->match($context, $rugs, null);

        $response = $this->authClient($this->tokenFor($user))
            ->request('GET', self::filtersUrl($context));

        self::assertResponseIsSuccessful();

        $data = self::decode($response);
        self::assertSame($context->getId()->toRfc4122(), $data['id']);

        self::assertSame(
            [
                ['id' => $tables->getId()->toRfc4122(), 'title' => 'Tables', 'count' => 2],
                ['id' => $lighting->getId()->toRfc4122(), 'title' => 'Lighting', 'count' => 1],
                ['id' => $rugs->getId()->toRfc4122(), 'title' => 'Rugs', 'count' => 1],
            ],
            $this->categoryFacets($data),
        );

        self::assertSame(['min' => 79, 'max' => 250], self::priceFacet($data));

        self::assertEqualsCanonicalizing(
            ['@context', '@id', '@type', 'id', 'categories', 'price'],
            array_keys($data),
        );
        self::assertEqualsCanonicalizing(['@type', 'id', 'title', 'count'], array_keys($data['categories'][0]));
        self::assertEqualsCanonicalizing(['@type', 'min', 'max'], array_keys((array) $data['price']));
    }

    public function testCategoryCountsAreDirectWithoutDescendantRollup(): void
    {
        $user = UserFactory::createOne();
        $context = $this->contextFor($user);
        $parent = CategoryFactory::createOne(['title' => 'Tables']);
        $child = CategoryFactory::createOne(['title' => 'Coffee tables', 'parent' => $parent]);

        $this->match($context, $parent, null);
        $this->match($context, $child, null);
        $this->match($context, $child, null);

        $data = self::decode($this->authClient($this->tokenFor($user))
            ->request('GET', self::filtersUrl($context)));

        self::assertSame(
            [
                ['id' => $child->getId()->toRfc4122(), 'title' => 'Coffee tables', 'count' => 2],
                ['id' => $parent->getId()->toRfc4122(), 'title' => 'Tables', 'count' => 1],
            ],
            $this->categoryFacets($data),
        );
    }

    public function testPriceFilterNarrowsCategoryCountsButKeepsTheFullListAndPriceBounds(): void
    {
        $user = UserFactory::createOne();
        $context = $this->contextFor($user);
        $tables = CategoryFactory::createOne(['title' => 'Tables']);
        $lighting = CategoryFactory::createOne(['title' => 'Lighting']);
        $rugs = CategoryFactory::createOne(['title' => 'Rugs']);

        $this->match($context, $tables, 10.0);
        $this->match($context, $tables, 100.0);
        $this->match($context, $lighting, 50.0);
        $this->match($context, $lighting, null);
        $this->match($context, $rugs, 15.0);

        $data = self::decode($this->authClient($this->tokenFor($user))
            ->request('GET', self::filtersUrl($context).'?priceMin=40'));

        self::assertSame(
            [
                ['id' => $lighting->getId()->toRfc4122(), 'title' => 'Lighting', 'count' => 1],
                ['id' => $tables->getId()->toRfc4122(), 'title' => 'Tables', 'count' => 1],
                ['id' => $rugs->getId()->toRfc4122(), 'title' => 'Rugs', 'count' => 0],
            ],
            $this->categoryFacets($data),
        );

        self::assertSame(['min' => 10, 'max' => 100], self::priceFacet($data));
    }

    public function testCategoryFilterNarrowsPriceBoundsWithDescendantsButNotTheCategoryList(): void
    {
        $user = UserFactory::createOne();
        $context = $this->contextFor($user);
        $parent = CategoryFactory::createOne(['title' => 'Tables']);
        $child = CategoryFactory::createOne(['title' => 'Coffee tables', 'parent' => $parent]);
        $lighting = CategoryFactory::createOne(['title' => 'Lighting']);

        $this->match($context, $parent, 100.0);
        $this->match($context, $child, 20.5);
        $this->match($context, $lighting, 300.0);

        $client = $this->authClient($this->tokenFor($user));
        $url = self::filtersUrl($context);

        $data = self::decode($client->request('GET', $url.'?category='.$parent->getId()));
        self::assertSame(['min' => 20, 'max' => 100], self::priceFacet($data));
        self::assertCount(3, $data['categories']);

        $data = self::decode($client->request('GET', $url.'?category='.Uuid::v7()->toRfc4122()));
        self::assertSame(['min' => 20, 'max' => 300], self::priceFacet($data));
        self::assertCount(3, $data['categories']);
    }

    public function testPriceAndCategoryFiltersEachNarrowOnlyTheOppositeFacet(): void
    {
        $user = UserFactory::createOne();
        $context = $this->contextFor($user);
        $parent = CategoryFactory::createOne(['title' => 'Tables']);
        $child = CategoryFactory::createOne(['title' => 'Coffee tables', 'parent' => $parent]);
        $lighting = CategoryFactory::createOne(['title' => 'Lighting']);

        $this->match($context, $parent, 100.0);
        $this->match($context, $child, 20.0);
        $this->match($context, $lighting, 300.0);

        $data = self::decode($this->authClient($this->tokenFor($user))
            ->request('GET', self::filtersUrl($context).'?category='.$parent->getId().'&priceMin=50'));

        self::assertSame(
            [
                ['id' => $lighting->getId()->toRfc4122(), 'title' => 'Lighting', 'count' => 1],
                ['id' => $parent->getId()->toRfc4122(), 'title' => 'Tables', 'count' => 1],
                ['id' => $child->getId()->toRfc4122(), 'title' => 'Coffee tables', 'count' => 0],
            ],
            $this->categoryFacets($data),
        );

        self::assertSame(['min' => 20, 'max' => 100], self::priceFacet($data));
    }

    public function testCategoryFilterCoveringOnlyUnpricedMatchesYieldsZeroPriceBounds(): void
    {
        $user = UserFactory::createOne();
        $context = $this->contextFor($user);
        $tables = CategoryFactory::createOne(['title' => 'Tables']);

        $this->match($context, $tables, null);
        $this->match($context, CategoryFactory::createOne(['title' => 'Lighting']), 50.0);

        $data = self::decode($this->authClient($this->tokenFor($user))
            ->request('GET', self::filtersUrl($context).'?category='.$tables->getId()));

        self::assertSame(['min' => 0, 'max' => 0], self::priceFacet($data));
    }

    public function testUnpricedMatchesOnlyYieldZeroPriceBounds(): void
    {
        $user = UserFactory::createOne();
        $context = $this->contextFor($user);
        $this->match($context, CategoryFactory::createOne(['title' => 'Rugs']), null);

        $data = self::decode($this->authClient($this->tokenFor($user))
            ->request('GET', self::filtersUrl($context)));

        self::assertSame(['min' => 0, 'max' => 0], self::priceFacet($data));
        self::assertCount(1, $data['categories']);
    }

    public function testContextWithoutMatchesReturnsEmptyFilters(): void
    {
        $user = UserFactory::createOne();
        $context = $this->contextFor($user);

        $data = self::decode($this->authClient($this->tokenFor($user))
            ->request('GET', self::filtersUrl($context)));

        self::assertResponseIsSuccessful();
        self::assertSame([], $data['categories']);
        self::assertSame(['min' => 0, 'max' => 0], self::priceFacet($data));
    }

    public function testAnonymousRequestReturns401(): void
    {
        $context = ProjectContextFactory::createOne();

        static::createClient()->request('GET', self::filtersUrl($context));

        self::assertResponseStatusCodeSame(401);
    }

    public function testOtherUsersProjectIsForbidden(): void
    {
        $stranger = UserFactory::createOne();
        $context = ProjectContextFactory::createOne();

        $this->authClient($this->tokenFor($stranger))
            ->request('GET', self::filtersUrl($context));

        self::assertResponseStatusCodeSame(403);
    }

    public function testUnknownProjectOrContextReturns404(): void
    {
        $user = UserFactory::createOne();
        $project = ProjectFactory::createOne(['user' => $user]);
        $otherProjectContext = ProjectContextFactory::createOne([
            'project' => ProjectFactory::new(['user' => $user]),
        ]);

        $client = $this->authClient($this->tokenFor($user));

        $client->request('GET', '/api/projects/'.Uuid::v7()->toRfc4122().'/contexts/'.Uuid::v7()->toRfc4122().'/matches/filters');
        self::assertResponseStatusCodeSame(404);

        $base = '/api/projects/'.$project->getId()->toRfc4122().'/contexts/';

        $client->request('GET', $base.Uuid::v7()->toRfc4122().'/matches/filters');
        self::assertResponseStatusCodeSame(404);

        $client->request('GET', $base.'not-a-uuid/matches/filters');
        self::assertResponseStatusCodeSame(404);

        $client->request('GET', $base.$otherProjectContext->getId()->toRfc4122().'/matches/filters');
        self::assertResponseStatusCodeSame(404);
    }

    public function testProcessingContextReturns202WithRetryAfter(): void
    {
        $user = UserFactory::createOne();
        $context = ProjectContextFactory::createOne([
            'project' => ProjectFactory::new(['user' => $user]),
        ]);
        ProjectProductMatchFactory::createOne(['context' => $context]);

        $this->authClient($this->tokenFor($user))
            ->request('GET', self::filtersUrl($context));

        self::assertResponseStatusCodeSame(202);
        self::assertResponseHeaderSame('Retry-After', '5');
        self::assertResponseHeaderSame('Content-Type', 'application/problem+json; charset=utf-8');
        self::assertJsonContains([
            'status' => 202,
            'detail' => 'Matching for this context is still running; retry shortly.',
        ]);
    }

    public function testInvalidQueryParametersAreRejected(): void
    {
        $user = UserFactory::createOne();
        $context = $this->contextFor($user);

        $client = $this->authClient($this->tokenFor($user));
        $url = self::filtersUrl($context);

        $client->request('GET', $url.'?priceMin=-1');
        self::assertResponseStatusCodeSame(422);

        $client->request('GET', $url.'?priceMax=-1');
        self::assertResponseStatusCodeSame(422);

        $client->request('GET', $url.'?priceMin=abc');
        self::assertResponseStatusCodeSame(422);

        $client->request('GET', $url.'?priceMin=10.5');
        self::assertResponseStatusCodeSame(422);

        $client->request('GET', $url.'?priceMax=10.5');
        self::assertResponseStatusCodeSame(422);

        $client->request('GET', $url.'?category=abc');
        self::assertResponseStatusCodeSame(422);

        $client->request('GET', $url.'?category=0');
        self::assertResponseStatusCodeSame(422);

        $client->request('GET', $url.'?priceMin=30&priceMax=10');
        self::assertResponseStatusCodeSame(422);
    }

    public function testFilterValidationOutranksResolution(): void
    {
        $user = UserFactory::createOne();
        $stranger = UserFactory::createOne();
        $project = ProjectFactory::createOne(['user' => $user]);
        $processing = ProjectContextFactory::createOne(['project' => $project]);
        $badFilters = '?priceMin=50&priceMax=10';

        $owner = $this->authClient($this->tokenFor($user));

        $owner->request('GET', '/api/projects/'.Uuid::v7()->toRfc4122().'/contexts/'.Uuid::v7()->toRfc4122().'/matches/filters'.$badFilters);
        self::assertResponseStatusCodeSame(422);

        $owner->request('GET', '/api/projects/'.$project->getId()->toRfc4122().'/contexts/'.Uuid::v7()->toRfc4122().'/matches/filters'.$badFilters);
        self::assertResponseStatusCodeSame(422);

        $owner->request('GET', self::filtersUrl($processing).$badFilters);
        self::assertResponseStatusCodeSame(422);

        $this->authClient($this->tokenFor($stranger))
            ->request('GET', self::filtersUrl($processing).$badFilters);
        self::assertResponseStatusCodeSame(422);

        $this->authClient($this->tokenFor($user))
            ->request('GET', '/api/projects/'.Uuid::v7()->toRfc4122().'/contexts/'.Uuid::v7()->toRfc4122().'/matches/filters');
        self::assertResponseStatusCodeSame(404);
    }

    private function contextFor(User $user): ProjectContext
    {
        return ProjectContextFactory::new([
            'project' => ProjectFactory::new(['user' => $user]),
        ])->completed()->create();
    }

    private static function filtersUrl(ProjectContext $context): string
    {
        return '/api/projects/'.$context->getProject()->getId()->toRfc4122()
            .'/contexts/'.$context->getId()->toRfc4122().'/matches/filters';
    }

    private function match(ProjectContext $context, Category $category, ?float $price): void
    {
        ProjectProductMatchFactory::createOne([
            'context' => $context,
            'product' => ProductFactory::createOne(['category' => $category, 'price' => $price]),
        ]);
    }

    /**
     * @param array{categories: list<array<string, mixed>>} $data
     *
     * @return list<array{id: mixed, title: mixed, count: mixed}>
     */
    private function categoryFacets(array $data): array
    {
        return array_map(static fn (array $category) => [
            'id' => $category['id'],
            'title' => $category['title'],
            'count' => $category['count'],
        ], $data['categories']);
    }

    /**
     * @param array{price: array{min: int, max: int}} $data
     *
     * @return array{min: int, max: int}
     */
    private static function priceFacet(array $data): array
    {
        return ['min' => $data['price']['min'], 'max' => $data['price']['max']];
    }

    /**
     * @return array{id: string, categories: list<array<string, mixed>>, price: array{min: int, max: int}}
     */
    private static function decode(ResponseInterface $response): array
    {
        /** @var array{id: string, categories: list<array<string, mixed>>, price: array{min: int, max: int}} $data */
        $data = $response->toArray();

        return $data;
    }
}
