<?php

namespace App\Tests\Application\Placement;

use App\Identity\Entity\User;
use App\Placement\Entity\ProductPlacement;
use App\Project\Entity\ProjectContext;
use App\Tests\Application\ApiTestCase;
use App\Tests\Factory\ProductFactory;
use App\Tests\Factory\ProductPlacementFactory;
use App\Tests\Factory\ProjectContextFactory;
use App\Tests\Factory\ProjectFactory;
use App\Tests\Factory\UserFactory;
use Symfony\Component\Uid\Uuid;

final class ReadPlacementTest extends ApiTestCase
{
    public function testReturnsProcessingPlacementWithNullResultFields(): void
    {
        $user = UserFactory::createOne();
        $context = $this->contextFor($user, 'a walnut table under the window');
        $product = ProductFactory::createOne();
        $placement = ProductPlacementFactory::createOne(['context' => $context, 'product' => $product]);

        $response = $this->authClient($this->tokenFor($user))
            ->request('GET', self::placementUrl($placement));

        self::assertResponseIsSuccessful();
        self::assertJsonContains([
            'id' => $placement->getId()->toRfc4122(),
            'status' => 'processing',
            'contextId' => $context->getId()->toRfc4122(),
            'productId' => $product->getId()->toRfc4122(),
            'prompt' => 'a walnut table under the window',
            'resultVersionId' => null,
            'resultImageUrl' => null,
        ]);

        /** @var array<string, mixed> $data */
        $data = $response->toArray();

        self::assertEqualsCanonicalizing(
            ['id', 'status', 'contextId', 'productId', 'prompt', 'resultVersionId', 'resultImageUrl', 'createdAt', 'updatedAt'],
            array_values(array_filter(array_keys($data), static fn (string $key) => !str_starts_with($key, '@'))),
            'The placement payload must expose exactly these fields.',
        );
    }

    public function testCompletedPlacementExposesItsResultVersionAndUrl(): void
    {
        $user = UserFactory::createOne();
        $context = $this->contextFor($user);
        $placement = ProductPlacementFactory::new(['context' => $context])
            ->completed('abc/placed.jpg')
            ->create();

        $this->authClient($this->tokenFor($user))->request('GET', self::placementUrl($placement));

        self::assertResponseIsSuccessful();

        $resultVersion = $placement->getResultVersion();
        self::assertNotNull($resultVersion);
        self::assertJsonContains([
            'status' => 'completed',
            'resultVersionId' => $resultVersion->getId()->toRfc4122(),
            'resultImageUrl' => 'http://localhost/uploads/project/abc/placed.jpg',
        ]);
    }

    public function testFailedPlacementReadsBackAsFailed(): void
    {
        $user = UserFactory::createOne();
        $placement = ProductPlacementFactory::new(['context' => $this->contextFor($user)])->failed()->create();

        $this->authClient($this->tokenFor($user))->request('GET', self::placementUrl($placement));

        self::assertResponseIsSuccessful();
        self::assertJsonContains(['status' => 'failed', 'resultVersionId' => null, 'resultImageUrl' => null]);
    }

    public function testRequiresAuthentication(): void
    {
        $placement = ProductPlacementFactory::createOne();

        static::createClient()->request('GET', self::placementUrl($placement));

        self::assertResponseStatusCodeSame(401);
    }

    public function testOtherUsersProjectIsForbidden(): void
    {
        $stranger = UserFactory::createOne();
        $placement = ProductPlacementFactory::createOne();

        $this->authClient($this->tokenFor($stranger))->request('GET', self::placementUrl($placement));

        self::assertResponseStatusCodeSame(403);
    }

    public function testUnknownProjectReturns404(): void
    {
        $user = UserFactory::createOne();
        $placement = ProductPlacementFactory::createOne(['context' => $this->contextFor($user)]);
        $placementId = $placement->getId()->toRfc4122();

        $client = $this->authClient($this->tokenFor($user));

        $client->request('GET', '/api/projects/'.Uuid::v7()->toRfc4122().'/placements/'.$placementId);
        self::assertResponseStatusCodeSame(404);

        $client->request('GET', '/api/projects/not-a-uuid/placements/'.$placementId);
        self::assertResponseStatusCodeSame(404);
    }

    public function testUnknownOrForeignPlacementReturns404(): void
    {
        $user = UserFactory::createOne();
        $project = ProjectFactory::createOne(['user' => $user]);
        $otherProjectPlacement = ProductPlacementFactory::createOne(['context' => $this->contextFor($user)]);

        $client = $this->authClient($this->tokenFor($user));
        $base = '/api/projects/'.$project->getId()->toRfc4122().'/placements/';

        $client->request('GET', $base.Uuid::v7()->toRfc4122());
        self::assertResponseStatusCodeSame(404);

        $client->request('GET', $base.'not-a-uuid');
        self::assertResponseStatusCodeSame(404);

        $client->request('GET', $base.$otherProjectPlacement->getId()->toRfc4122());
        self::assertResponseStatusCodeSame(404);
    }

    private function contextFor(User $user, ?string $prompt = null): ProjectContext
    {
        $attributes = ['project' => ProjectFactory::new(['user' => $user])];

        if (null !== $prompt) {
            $attributes['prompt'] = $prompt;
        }

        return ProjectContextFactory::new($attributes)->completed()->create();
    }

    private static function placementUrl(ProductPlacement $placement): string
    {
        return '/api/projects/'.$placement->getProject()->getId()->toRfc4122()
            .'/placements/'.$placement->getId()->toRfc4122();
    }
}
