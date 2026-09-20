<?php

namespace App\Tests\Application\Visualization;

use App\Identity\Entity\User;
use App\Visualization\Entity\ProductVisualization;
use App\Project\Entity\ProjectContext;
use App\Tests\Application\ApiTestCase;
use App\Tests\Factory\ProductFactory;
use App\Tests\Factory\ProductVisualizationFactory;
use App\Tests\Factory\ProjectContextFactory;
use App\Tests\Factory\ProjectFactory;
use App\Tests\Factory\UserFactory;
use Symfony\Component\Uid\Uuid;

final class ReadVisualizationTest extends ApiTestCase
{
    public function testReturnsProcessingVisualizationWithNullResultFields(): void
    {
        $user = UserFactory::createOne();
        $context = $this->contextFor($user, 'a walnut table under the window');
        $product = ProductFactory::createOne();
        $visualization = ProductVisualizationFactory::createOne(['context' => $context, 'product' => $product]);

        $response = $this->authClient($this->tokenFor($user))
            ->request('GET', self::visualizationUrl($visualization));

        self::assertResponseIsSuccessful();
        self::assertJsonContains([
            'id' => $visualization->getId()->toRfc4122(),
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
            'The visualization payload must expose exactly these fields.',
        );
    }

    public function testCompletedVisualizationExposesItsResultVersionAndUrl(): void
    {
        $user = UserFactory::createOne();
        $context = $this->contextFor($user);
        $visualization = ProductVisualizationFactory::new(['context' => $context])
            ->completed('abc/placed.jpg')
            ->create();

        $this->authClient($this->tokenFor($user))->request('GET', self::visualizationUrl($visualization));

        self::assertResponseIsSuccessful();

        $resultVersion = $visualization->getResultVersion();
        self::assertNotNull($resultVersion);
        self::assertJsonContains([
            'status' => 'completed',
            'resultVersionId' => $resultVersion->getId()->toRfc4122(),
            'resultImageUrl' => 'http://localhost/uploads/project/abc/placed.jpg',
        ]);
    }

    public function testFailedVisualizationReadsBackAsFailed(): void
    {
        $user = UserFactory::createOne();
        $visualization = ProductVisualizationFactory::new(['context' => $this->contextFor($user)])->failed()->create();

        $this->authClient($this->tokenFor($user))->request('GET', self::visualizationUrl($visualization));

        self::assertResponseIsSuccessful();
        self::assertJsonContains(['status' => 'failed', 'resultVersionId' => null, 'resultImageUrl' => null]);
    }

    public function testRequiresAuthentication(): void
    {
        $visualization = ProductVisualizationFactory::createOne();

        static::createClient()->request('GET', self::visualizationUrl($visualization));

        self::assertResponseStatusCodeSame(401);
    }

    public function testOtherUsersVisualizationIsForbidden(): void
    {
        $stranger = UserFactory::createOne();
        $visualization = ProductVisualizationFactory::createOne();

        $this->authClient($this->tokenFor($stranger))->request('GET', self::visualizationUrl($visualization));

        self::assertResponseStatusCodeSame(403);
    }

    public function testUnknownVisualizationReturns404(): void
    {
        $client = $this->authClient($this->tokenFor(UserFactory::createOne()));

        $client->request('GET', '/api/visualizations/'.Uuid::v7()->toRfc4122());
        self::assertResponseStatusCodeSame(404);

        $client->request('GET', '/api/visualizations/not-a-uuid');
        self::assertResponseStatusCodeSame(404);
    }

    public function testAnyOwnedVisualizationIsReachableRegardlessOfProject(): void
    {
        $user = UserFactory::createOne();
        ProjectFactory::createOne(['user' => $user]);
        $visualization = ProductVisualizationFactory::createOne(['context' => $this->contextFor($user)]);

        $this->authClient($this->tokenFor($user))->request('GET', self::visualizationUrl($visualization));

        self::assertResponseIsSuccessful();
        self::assertJsonContains(['id' => $visualization->getId()->toRfc4122()]);
    }

    private function contextFor(User $user, ?string $prompt = null): ProjectContext
    {
        $attributes = ['project' => ProjectFactory::new(['user' => $user])];

        if (null !== $prompt) {
            $attributes['prompt'] = $prompt;
        }

        return ProjectContextFactory::new($attributes)->completed()->create();
    }

    private static function visualizationUrl(ProductVisualization $visualization): string
    {
        return '/api/visualizations/'.$visualization->getId()->toRfc4122();
    }
}
