<?php

namespace App\Tests\Application\CatalogSearch;

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

final class ProjectMatchListTest extends ApiTestCase
{
    public function testListsMatchesBestFirstWithProductDetails(): void
    {
        $user = UserFactory::createOne();
        $context = $this->contextFor($user);
        $product = ProductFactory::createOne([
            'title' => 'Walnut coffee table',
            'description' => 'Mid-century walnut coffee table.',
            'url' => 'https://shop.example.com/walnut-coffee-table',
            'thumbnailUrl' => 'abc/thumbnail.jpg',
            'price' => 249.5,
            'widthSm' => 120.0,
        ]);
        ProjectProductMatchFactory::createOne(['context' => $context, 'product' => $product, 'matchScore' => 0.91]);
        ProjectProductMatchFactory::createOne(['context' => $context, 'matchScore' => 0.62]);
        ProjectProductMatchFactory::createOne(['context' => $context, 'matchScore' => 0.77]);

        $response = $this->authClient($this->tokenFor($user))
            ->request('GET', self::matchesUrl($context));

        self::assertResponseIsSuccessful();

        $data = self::decode($response);
        self::assertSame(3, $data['totalItems']);
        self::assertSame([0.91, 0.77, 0.62], array_column($data['member'], 'score'));

        $best = $data['member'][0];
        self::assertSame($product->getId()->toRfc4122(), $best['id']);
        self::assertSame('Walnut coffee table', $best['title']);
        self::assertSame(249.5, $best['price']);
        self::assertSame('http://localhost/uploads/product/abc/thumbnail.jpg', $best['imageUrl']);
        self::assertSame(0.91, $best['score']);
        self::assertSame('https://shop.example.com/walnut-coffee-table', $best['url']);

        // Asserted as an exact set rather than a denylist: a denylist cannot catch a field
        // added to Product later, and its entries quietly go vacuous when one is removed.
        self::assertEqualsCanonicalizing(
            ['@id', '@type', 'id', 'title', 'price', 'imageUrl', 'score', 'url'],
            array_keys($best),
            'The match payload must expose exactly these fields — no Product or match internals.',
        );
    }

    public function testListsOnlyTheRequestedContextsMatches(): void
    {
        $user = UserFactory::createOne();
        $project = ProjectFactory::createOne(['user' => $user]);
        $context = ProjectContextFactory::new(['project' => $project])->completed()->create();
        $sibling = ProjectContextFactory::new(['project' => $project])->completed()->create();

        $product = ProductFactory::createOne();
        ProjectProductMatchFactory::createOne(['context' => $context, 'product' => $product, 'matchScore' => 0.9]);
        ProjectProductMatchFactory::createOne(['context' => $sibling, 'matchScore' => 0.95]);

        $data = self::decode($this->authClient($this->tokenFor($user))
            ->request('GET', self::matchesUrl($context)));

        self::assertSame(1, $data['totalItems']);
        self::assertSame([$product->getId()->toRfc4122()], array_column($data['member'], 'id'));
    }

    public function testPriceBoundsFilterAndExcludeUnpricedProducts(): void
    {
        $user = UserFactory::createOne();
        $context = $this->contextFor($user);
        $this->matchWithPrice($context, 10.0);
        $this->matchWithPrice($context, 50.0);
        $this->matchWithPrice($context, null);

        $client = $this->authClient($this->tokenFor($user));
        $url = self::matchesUrl($context);

        $data = self::decode($client->request('GET', $url.'?priceMin=20'));
        self::assertSame([50.0], $this->memberPrices($data));

        $data = self::decode($client->request('GET', $url.'?priceMax=20'));
        self::assertSame([10.0], $this->memberPrices($data));

        $data = self::decode($client->request('GET', $url.'?priceMin=5&priceMax=60'));
        self::assertSame(2, $data['totalItems']);
    }

    public function testCategoryFilterIncludesDescendantCategories(): void
    {
        $user = UserFactory::createOne();
        $context = $this->contextFor($user);
        $parent = CategoryFactory::createOne(['title' => 'Tables']);
        $child = CategoryFactory::createOne(['title' => 'Coffee tables', 'parent' => $parent]);
        $other = CategoryFactory::createOne(['title' => 'Lighting']);

        $inParent = ProductFactory::createOne(['category' => $parent]);
        $inChild = ProductFactory::createOne(['category' => $child]);
        $inOther = ProductFactory::createOne(['category' => $other]);
        ProjectProductMatchFactory::createOne(['context' => $context, 'product' => $inParent]);
        ProjectProductMatchFactory::createOne(['context' => $context, 'product' => $inChild]);
        ProjectProductMatchFactory::createOne(['context' => $context, 'product' => $inOther]);

        $client = $this->authClient($this->tokenFor($user));
        $url = self::matchesUrl($context);

        $data = self::decode($client->request('GET', $url.'?category='.$parent->getId()));
        self::assertSame(2, $data['totalItems']);
        self::assertEqualsCanonicalizing(
            [$inParent->getId()->toRfc4122(), $inChild->getId()->toRfc4122()],
            array_column($data['member'], 'id'),
        );

        $data = self::decode($client->request('GET', $url.'?category='.$child->getId()));
        self::assertSame([$inChild->getId()->toRfc4122()], array_column($data['member'], 'id'));

        $data = self::decode($client->request('GET', $url.'?category='.Uuid::v7()->toRfc4122()));
        self::assertSame(0, $data['totalItems']);
        self::assertSame([], $data['member']);
    }

    public function testSortByPricePutsUnpricedProductsLastInBothDirections(): void
    {
        $user = UserFactory::createOne();
        $context = $this->contextFor($user);
        $this->matchWithPrice($context, 30.0);
        $this->matchWithPrice($context, 10.0);
        $this->matchWithPrice($context, null);

        $client = $this->authClient($this->tokenFor($user));
        $url = self::matchesUrl($context);

        $data = self::decode($client->request('GET', $url.'?sort=price&direction=asc'));
        self::assertSame([10.0, 30.0, null], $this->memberPrices($data));

        $data = self::decode($client->request('GET', $url.'?sort=price&direction=desc'));
        self::assertSame([30.0, 10.0, null], $this->memberPrices($data));
    }

    public function testSortByScoreAscendingReversesDefaultOrder(): void
    {
        $user = UserFactory::createOne();
        $context = $this->contextFor($user);
        ProjectProductMatchFactory::createOne(['context' => $context, 'matchScore' => 0.91]);
        ProjectProductMatchFactory::createOne(['context' => $context, 'matchScore' => 0.62]);
        ProjectProductMatchFactory::createOne(['context' => $context, 'matchScore' => 0.77]);

        $data = self::decode($this->authClient($this->tokenFor($user))
            ->request('GET', self::matchesUrl($context).'?sort=score&direction=asc'));

        self::assertSame([0.62, 0.77, 0.91], array_column($data['member'], 'score'));
    }

    public function testCombinedCategoryAndPriceFiltersIntersect(): void
    {
        $user = UserFactory::createOne();
        $context = $this->contextFor($user);
        $tables = CategoryFactory::createOne(['title' => 'Tables']);
        $lighting = CategoryFactory::createOne(['title' => 'Lighting']);

        $cheapTable = ProductFactory::createOne(['category' => $tables, 'price' => 10.0]);
        $priceyTable = ProductFactory::createOne(['category' => $tables, 'price' => 100.0]);
        $priceyLamp = ProductFactory::createOne(['category' => $lighting, 'price' => 80.0]);
        ProjectProductMatchFactory::createOne(['context' => $context, 'product' => $cheapTable]);
        ProjectProductMatchFactory::createOne(['context' => $context, 'product' => $priceyTable]);
        ProjectProductMatchFactory::createOne(['context' => $context, 'product' => $priceyLamp]);

        $data = self::decode($this->authClient($this->tokenFor($user))
            ->request('GET', self::matchesUrl($context).'?category='.$tables->getId().'&priceMin=50'));

        self::assertSame(1, $data['totalItems']);
        self::assertSame($priceyTable->getId()->toRfc4122(), $data['member'][0]['id']);
    }

    public function testEqualScoresAndPricesTiebreakByProductIdForStablePagination(): void
    {
        $user = UserFactory::createOne();
        $context = $this->contextFor($user);
        $first = ProductFactory::createOne(['price' => 20.0]);
        $second = ProductFactory::createOne(['price' => 20.0]);
        ProjectProductMatchFactory::createOne(['context' => $context, 'product' => $second, 'matchScore' => 0.8]);
        ProjectProductMatchFactory::createOne(['context' => $context, 'product' => $first, 'matchScore' => 0.8]);

        $client = $this->authClient($this->tokenFor($user));
        $url = self::matchesUrl($context);
        $expected = [$first->getId()->toRfc4122(), $second->getId()->toRfc4122()];

        $data = self::decode($client->request('GET', $url));
        self::assertSame($expected, array_column($data['member'], 'id'));

        $data = self::decode($client->request('GET', $url.'?sort=price&direction=desc'));
        self::assertSame($expected, array_column($data['member'], 'id'));
    }

    public function testPaginatesWithFifteenItemsPerPage(): void
    {
        $user = UserFactory::createOne();
        $context = $this->contextFor($user);
        ProjectProductMatchFactory::createMany(20, ['context' => $context]);

        $client = $this->authClient($this->tokenFor($user));
        $url = self::matchesUrl($context);

        $data = self::decode($client->request('GET', $url));
        self::assertSame(20, $data['totalItems']);
        self::assertCount(15, $data['member']);

        $data = self::decode($client->request('GET', $url.'?page=2'));
        self::assertCount(5, $data['member']);
    }

    public function testAnonymousRequestReturns401(): void
    {
        $context = ProjectContextFactory::createOne();

        static::createClient()->request('GET', self::matchesUrl($context));

        self::assertResponseStatusCodeSame(401);
    }

    public function testOtherUsersProjectIsForbidden(): void
    {
        $stranger = UserFactory::createOne();
        $context = ProjectContextFactory::createOne();

        $this->authClient($this->tokenFor($stranger))
            ->request('GET', self::matchesUrl($context));

        self::assertResponseStatusCodeSame(403);
    }

    public function testUnknownProjectReturns404(): void
    {
        $user = UserFactory::createOne();
        $contextId = Uuid::v7()->toRfc4122();

        $client = $this->authClient($this->tokenFor($user));

        $client->request('GET', '/api/projects/'.Uuid::v7()->toRfc4122().'/contexts/'.$contextId.'/matches');
        self::assertResponseStatusCodeSame(404);

        $client->request('GET', '/api/projects/not-a-uuid/contexts/'.$contextId.'/matches');
        self::assertResponseStatusCodeSame(404);
    }

    public function testUnknownOrForeignContextReturns404(): void
    {
        $user = UserFactory::createOne();
        $project = ProjectFactory::createOne(['user' => $user]);
        $otherProjectContext = ProjectContextFactory::createOne([
            'project' => ProjectFactory::new(['user' => $user]),
        ]);

        $client = $this->authClient($this->tokenFor($user));
        $base = '/api/projects/'.$project->getId()->toRfc4122().'/contexts/';

        $client->request('GET', $base.Uuid::v7()->toRfc4122().'/matches');
        self::assertResponseStatusCodeSame(404);

        $client->request('GET', $base.'not-a-uuid/matches');
        self::assertResponseStatusCodeSame(404);

        // A real context id nested under the wrong project is not found either.
        $client->request('GET', $base.$otherProjectContext->getId()->toRfc4122().'/matches');
        self::assertResponseStatusCodeSame(404);
    }

    public function testProcessingContextReturns202WithRetryAfter(): void
    {
        $user = UserFactory::createOne();
        $context = ProjectContextFactory::createOne([
            'project' => ProjectFactory::new(['user' => $user]),
        ]);
        ProjectProductMatchFactory::createOne(['context' => $context]);

        $response = $this->authClient($this->tokenFor($user))
            ->request('GET', self::matchesUrl($context));

        self::assertResponseStatusCodeSame(202);
        self::assertResponseHeaderSame('Retry-After', '5');
        self::assertJsonContains(['status' => 'processing']);
    }

    public function testContextWithoutMatchesReturnsEmptyCollection(): void
    {
        $user = UserFactory::createOne();
        $context = $this->contextFor($user);

        $data = self::decode($this->authClient($this->tokenFor($user))
            ->request('GET', self::matchesUrl($context)));

        self::assertResponseIsSuccessful();
        self::assertSame(0, $data['totalItems']);
        self::assertSame([], $data['member']);
    }

    public function testInvalidQueryParametersAreRejected(): void
    {
        $user = UserFactory::createOne();
        $context = $this->contextFor($user);

        $client = $this->authClient($this->tokenFor($user));
        $url = self::matchesUrl($context);

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

        // Price filters accept whole units only.
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

    private function contextFor(User $user): ProjectContext
    {
        return ProjectContextFactory::new([
            'project' => ProjectFactory::new(['user' => $user]),
        ])->completed()->create();
    }

    private static function matchesUrl(ProjectContext $context): string
    {
        return '/api/projects/'.$context->getProject()->getId()->toRfc4122()
            .'/contexts/'.$context->getId()->toRfc4122().'/matches';
    }

    private function matchWithPrice(ProjectContext $context, ?float $price): void
    {
        ProjectProductMatchFactory::createOne([
            'context' => $context,
            'product' => ProductFactory::createOne(['price' => $price]),
        ]);
    }

    /**
     * @param array{member: list<array{price: float|null}>} $data
     *
     * @return list<float|null>
     */
    private function memberPrices(array $data): array
    {
        return array_map(
            static fn (array $member) => null === $member['price'] ? null : (float) $member['price'],
            $data['member'],
        );
    }

    /**
     * @return array{totalItems: int, member: list<array{id: string, title: string, price: float|null, imageUrl: string, score: float, url: string}>}
     */
    private static function decode(ResponseInterface $response): array
    {
        /** @var array{totalItems: int, member: list<array{id: string, title: string, price: float|null, imageUrl: string, score: float, url: string}>} $data */
        $data = $response->toArray();

        return $data;
    }
}
