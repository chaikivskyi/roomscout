<?php

namespace App\Tests\Application\CatalogSearch;

use App\Project\Entity\Project;
use App\Tests\Application\ApiTestCase;
use App\Tests\Factory\CategoryFactory;
use App\Tests\Factory\ProductFactory;
use App\Tests\Factory\ProjectFactory;
use App\Tests\Factory\ProjectProductMatchFactory;
use App\Tests\Factory\UserFactory;
use Symfony\Component\Uid\Uuid;

final class ProjectMatchListTest extends ApiTestCase
{
    public function testListsMatchesBestFirstWithProductDetails(): void
    {
        $user = UserFactory::createOne();
        $project = ProjectFactory::createOne(['user' => $user]);
        $product = ProductFactory::createOne([
            'title' => 'Walnut coffee table',
            'description' => 'Mid-century walnut coffee table.',
            'url' => 'https://shop.example.com/walnut-coffee-table',
            'thumbnailUrl' => 'abc/thumbnail.jpg',
            'price' => 249.5,
            'widthSm' => 120.0,
        ]);
        ProjectProductMatchFactory::createOne(['project' => $project, 'product' => $product, 'matchScore' => 0.91]);
        ProjectProductMatchFactory::createOne(['project' => $project, 'matchScore' => 0.62]);
        ProjectProductMatchFactory::createOne(['project' => $project, 'matchScore' => 0.77]);

        $response = $this->authClient($this->tokenFor($user))
            ->request('GET', '/api/projects/'.$project->getId()->toRfc4122().'/matches');

        self::assertResponseIsSuccessful();

        $data = $response->toArray();
        self::assertSame(3, $data['totalItems']);
        self::assertSame([0.91, 0.77, 0.62], array_column($data['member'], 'score'));

        $best = $data['member'][0];
        self::assertSame($product->getUuid()->toRfc4122(), $best['id']);
        self::assertSame('Walnut coffee table', $best['title']);
        self::assertSame(249.5, $best['price']);
        self::assertSame('http://localhost/uploads/product/abc/thumbnail.jpg', $best['imageUrl']);
        self::assertSame(0.91, $best['score']);
        self::assertSame('https://shop.example.com/walnut-coffee-table', $best['url']);

        foreach (['description', 'category', 'widthSm', 'heightSm', 'depthSm', 'model', 'matchedAt', 'externalId', 'uuid'] as $key) {
            self::assertArrayNotHasKey($key, $best);
        }
    }

    public function testPriceBoundsFilterAndExcludeUnpricedProducts(): void
    {
        $user = UserFactory::createOne();
        $project = ProjectFactory::createOne(['user' => $user]);
        $this->matchWithPrice($project, 10.0);
        $this->matchWithPrice($project, 50.0);
        $this->matchWithPrice($project, null);

        $client = $this->authClient($this->tokenFor($user));
        $url = '/api/projects/'.$project->getId()->toRfc4122().'/matches';

        $data = $client->request('GET', $url.'?priceMin=20')->toArray();
        self::assertSame([50.0], $this->memberPrices($data));

        $data = $client->request('GET', $url.'?priceMax=20')->toArray();
        self::assertSame([10.0], $this->memberPrices($data));

        $data = $client->request('GET', $url.'?priceMin=5&priceMax=60')->toArray();
        self::assertSame(2, $data['totalItems']);
    }

    public function testCategoryFilterIncludesDescendantCategories(): void
    {
        $user = UserFactory::createOne();
        $project = ProjectFactory::createOne(['user' => $user]);
        $parent = CategoryFactory::createOne(['title' => 'Tables']);
        $child = CategoryFactory::createOne(['title' => 'Coffee tables', 'parent' => $parent]);
        $other = CategoryFactory::createOne(['title' => 'Lighting']);

        $inParent = ProductFactory::createOne(['category' => $parent]);
        $inChild = ProductFactory::createOne(['category' => $child]);
        $inOther = ProductFactory::createOne(['category' => $other]);
        ProjectProductMatchFactory::createOne(['project' => $project, 'product' => $inParent]);
        ProjectProductMatchFactory::createOne(['project' => $project, 'product' => $inChild]);
        ProjectProductMatchFactory::createOne(['project' => $project, 'product' => $inOther]);

        $client = $this->authClient($this->tokenFor($user));
        $url = '/api/projects/'.$project->getId()->toRfc4122().'/matches';

        $data = $client->request('GET', $url.'?category='.$parent->getId())->toArray();
        self::assertSame(2, $data['totalItems']);
        self::assertEqualsCanonicalizing(
            [$inParent->getUuid()->toRfc4122(), $inChild->getUuid()->toRfc4122()],
            array_column($data['member'], 'id'),
        );

        $data = $client->request('GET', $url.'?category='.$child->getId())->toArray();
        self::assertSame([$inChild->getUuid()->toRfc4122()], array_column($data['member'], 'id'));

        $data = $client->request('GET', $url.'?category=999999')->toArray();
        self::assertSame(0, $data['totalItems']);
        self::assertSame([], $data['member']);
    }

    public function testSortByPricePutsUnpricedProductsLastInBothDirections(): void
    {
        $user = UserFactory::createOne();
        $project = ProjectFactory::createOne(['user' => $user]);
        $this->matchWithPrice($project, 30.0);
        $this->matchWithPrice($project, 10.0);
        $this->matchWithPrice($project, null);

        $client = $this->authClient($this->tokenFor($user));
        $url = '/api/projects/'.$project->getId()->toRfc4122().'/matches';

        $data = $client->request('GET', $url.'?sort=price&direction=asc')->toArray();
        self::assertSame([10.0, 30.0, null], $this->memberPrices($data));

        $data = $client->request('GET', $url.'?sort=price&direction=desc')->toArray();
        self::assertSame([30.0, 10.0, null], $this->memberPrices($data));
    }

    public function testSortByScoreAscendingReversesDefaultOrder(): void
    {
        $user = UserFactory::createOne();
        $project = ProjectFactory::createOne(['user' => $user]);
        ProjectProductMatchFactory::createOne(['project' => $project, 'matchScore' => 0.91]);
        ProjectProductMatchFactory::createOne(['project' => $project, 'matchScore' => 0.62]);
        ProjectProductMatchFactory::createOne(['project' => $project, 'matchScore' => 0.77]);

        $data = $this->authClient($this->tokenFor($user))
            ->request('GET', '/api/projects/'.$project->getId()->toRfc4122().'/matches?sort=score&direction=asc')
            ->toArray();

        self::assertSame([0.62, 0.77, 0.91], array_column($data['member'], 'score'));
    }

    public function testCombinedCategoryAndPriceFiltersIntersect(): void
    {
        $user = UserFactory::createOne();
        $project = ProjectFactory::createOne(['user' => $user]);
        $tables = CategoryFactory::createOne(['title' => 'Tables']);
        $lighting = CategoryFactory::createOne(['title' => 'Lighting']);

        $cheapTable = ProductFactory::createOne(['category' => $tables, 'price' => 10.0]);
        $priceyTable = ProductFactory::createOne(['category' => $tables, 'price' => 100.0]);
        $priceyLamp = ProductFactory::createOne(['category' => $lighting, 'price' => 80.0]);
        ProjectProductMatchFactory::createOne(['project' => $project, 'product' => $cheapTable]);
        ProjectProductMatchFactory::createOne(['project' => $project, 'product' => $priceyTable]);
        ProjectProductMatchFactory::createOne(['project' => $project, 'product' => $priceyLamp]);

        $data = $this->authClient($this->tokenFor($user))
            ->request('GET', '/api/projects/'.$project->getId()->toRfc4122().'/matches?category='.$tables->getId().'&priceMin=50')
            ->toArray();

        self::assertSame(1, $data['totalItems']);
        self::assertSame($priceyTable->getUuid()->toRfc4122(), $data['member'][0]['id']);
    }

    public function testEqualScoresAndPricesTiebreakByProductIdForStablePagination(): void
    {
        $user = UserFactory::createOne();
        $project = ProjectFactory::createOne(['user' => $user]);
        $first = ProductFactory::createOne(['price' => 20.0]);
        $second = ProductFactory::createOne(['price' => 20.0]);
        ProjectProductMatchFactory::createOne(['project' => $project, 'product' => $second, 'matchScore' => 0.8]);
        ProjectProductMatchFactory::createOne(['project' => $project, 'product' => $first, 'matchScore' => 0.8]);

        $client = $this->authClient($this->tokenFor($user));
        $url = '/api/projects/'.$project->getId()->toRfc4122().'/matches';
        $expected = [$first->getUuid()->toRfc4122(), $second->getUuid()->toRfc4122()];

        $data = $client->request('GET', $url)->toArray();
        self::assertSame($expected, array_column($data['member'], 'id'));

        $data = $client->request('GET', $url.'?sort=price&direction=desc')->toArray();
        self::assertSame($expected, array_column($data['member'], 'id'));
    }

    public function testPaginatesWithFifteenItemsPerPage(): void
    {
        $user = UserFactory::createOne();
        $project = ProjectFactory::createOne(['user' => $user]);
        ProjectProductMatchFactory::createMany(20, ['project' => $project]);

        $client = $this->authClient($this->tokenFor($user));
        $url = '/api/projects/'.$project->getId()->toRfc4122().'/matches';

        $data = $client->request('GET', $url)->toArray();
        self::assertSame(20, $data['totalItems']);
        self::assertCount(15, $data['member']);

        $data = $client->request('GET', $url.'?page=2')->toArray();
        self::assertCount(5, $data['member']);
    }

    public function testAnonymousRequestReturns401(): void
    {
        $project = ProjectFactory::createOne();

        static::createClient()->request('GET', '/api/projects/'.$project->getId()->toRfc4122().'/matches');

        self::assertResponseStatusCodeSame(401);
    }

    public function testOtherUsersProjectIsForbidden(): void
    {
        $stranger = UserFactory::createOne();
        $project = ProjectFactory::createOne();

        $this->authClient($this->tokenFor($stranger))
            ->request('GET', '/api/projects/'.$project->getId()->toRfc4122().'/matches');

        self::assertResponseStatusCodeSame(403);
    }

    public function testUnknownProjectReturns404(): void
    {
        $user = UserFactory::createOne();

        $client = $this->authClient($this->tokenFor($user));

        $client->request('GET', '/api/projects/'.Uuid::v7()->toRfc4122().'/matches');
        self::assertResponseStatusCodeSame(404);

        $client->request('GET', '/api/projects/not-a-uuid/matches');
        self::assertResponseStatusCodeSame(404);
    }

    public function testProjectWithoutMatchesReturnsEmptyCollection(): void
    {
        $user = UserFactory::createOne();
        $project = ProjectFactory::createOne(['user' => $user]);

        $data = $this->authClient($this->tokenFor($user))
            ->request('GET', '/api/projects/'.$project->getId()->toRfc4122().'/matches')
            ->toArray();

        self::assertResponseIsSuccessful();
        self::assertSame(0, $data['totalItems']);
        self::assertSame([], $data['member']);
    }

    public function testInvalidQueryParametersAreRejected(): void
    {
        $user = UserFactory::createOne();
        $project = ProjectFactory::createOne(['user' => $user]);

        $client = $this->authClient($this->tokenFor($user));
        $url = '/api/projects/'.$project->getId()->toRfc4122().'/matches';

        $client->request('GET', $url.'?sort=bogus');
        self::assertResponseStatusCodeSame(422);

        $client->request('GET', $url.'?direction=sideways');
        self::assertResponseStatusCodeSame(422);

        $client->request('GET', $url.'?priceMin=-1');
        self::assertResponseStatusCodeSame(422);

        $client->request('GET', $url.'?priceMax=-1');
        self::assertResponseStatusCodeSame(422);

        $client->request('GET', $url.'?priceMin=abc');
        self::assertResponseStatusCodeSame(422);

        $client->request('GET', $url.'?category=abc');
        self::assertResponseStatusCodeSame(422);

        $client->request('GET', $url.'?category=0');
        self::assertResponseStatusCodeSame(422);

        $client->request('GET', $url.'?priceMin=30&priceMax=10');
        self::assertResponseStatusCodeSame(422);
    }

    private function matchWithPrice(Project $project, ?float $price): void
    {
        ProjectProductMatchFactory::createOne([
            'project' => $project,
            'product' => ProductFactory::createOne(['price' => $price]),
        ]);
    }

    /**
     * @return list<float|null>
     */
    private function memberPrices(array $data): array
    {
        return array_map(
            static fn (array $member) => null === $member['price'] ? null : (float) $member['price'],
            $data['member'],
        );
    }
}
