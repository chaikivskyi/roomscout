<?php

namespace App\Tests\Application\Placement;

use App\Identity\Entity\User;
use App\Placement\Command\GeneratePlacementImage;
use App\Placement\Entity\ProductPlacement;
use App\Placement\Enum\PlacementStatus;
use App\Project\Entity\Project;
use App\Project\Entity\ProjectContext;
use App\Tests\Application\ApiTestCase;
use App\Tests\Factory\ProductFactory;
use App\Tests\Factory\ProductPlacementFactory;
use App\Tests\Factory\ProjectContextFactory;
use App\Tests\Factory\ProjectFactory;
use App\Tests\Factory\ProjectProductMatchFactory;
use App\Tests\Factory\UserFactory;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Symfony\Component\Messenger\Transport\InMemory\InMemoryTransport;
use Symfony\Component\Uid\Uuid;

final class CreatePlacementTest extends ApiTestCase
{
    public function testCreatesPlacementForMatchedProduct(): void
    {
        $user = UserFactory::createOne();
        $context = $this->contextFor($user, 'a walnut table under the window');
        $product = ProductFactory::createOne();
        ProjectProductMatchFactory::createOne(['context' => $context, 'product' => $product]);

        $response = $this->authClient($this->tokenFor($user))
            ->request('POST', self::placementsUrl($context->getProject()), [
                'json' => [
                    'contextId' => $context->getId()->toRfc4122(),
                    'productId' => $product->getId()->toRfc4122(),
                ],
            ]);

        self::assertResponseStatusCodeSame(201);
        self::assertJsonContains([
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

        self::assertIsString($data['id']);
        $placement = $this->entityManager()->getRepository(ProductPlacement::class)
            ->find(Uuid::fromString($data['id']));
        self::assertNotNull($placement);
        self::assertSame(PlacementStatus::Processing, $placement->getStatus());
        self::assertSame('a walnut table under the window', $placement->getPrompt());

        $messages = $this->generationMessages();
        self::assertCount(1, $messages);
        self::assertSame($data['id'], $messages[0]->placementId);
    }

    public function testRejectsWhileAnotherPlacementOfTheProjectIsProcessing(): void
    {
        $user = UserFactory::createOne();
        $context = $this->contextFor($user);
        $product = ProductFactory::createOne();
        ProjectProductMatchFactory::createOne(['context' => $context, 'product' => $product]);

        $sibling = ProjectContextFactory::new(['project' => $context->getProject()])->completed()->create();
        ProductPlacementFactory::createOne(['context' => $sibling]);

        $this->authClient($this->tokenFor($user))
            ->request('POST', self::placementsUrl($context->getProject()), [
                'json' => [
                    'contextId' => $context->getId()->toRfc4122(),
                    'productId' => $product->getId()->toRfc4122(),
                ],
            ]);

        self::assertResponseStatusCodeSame(409);
    }

    public function testTerminalPlacementsDoNotBlockANewOne(): void
    {
        $user = UserFactory::createOne();
        $context = $this->contextFor($user);
        $product = ProductFactory::createOne();
        ProjectProductMatchFactory::createOne(['context' => $context, 'product' => $product]);

        ProductPlacementFactory::new(['context' => $context])->completed()->create();
        ProductPlacementFactory::new(['context' => $context])->failed()->create();

        $this->authClient($this->tokenFor($user))
            ->request('POST', self::placementsUrl($context->getProject()), [
                'json' => [
                    'contextId' => $context->getId()->toRfc4122(),
                    'productId' => $product->getId()->toRfc4122(),
                ],
            ]);

        self::assertResponseStatusCodeSame(201);
    }

    public function testOnlyOneProcessingPlacementPerProjectAtTheDatabaseLevel(): void
    {
        $placement = ProductPlacementFactory::createOne();
        $sibling = ProjectContextFactory::new(['project' => $placement->getProject()])->completed()->create();
        $product = ProductFactory::createOne();

        $entityManager = $this->entityManager();
        $entityManager->persist(new ProductPlacement($placement->getProject(), $sibling, $product, 'gemini-test-image'));

        $this->expectException(UniqueConstraintViolationException::class);
        $entityManager->flush();
    }

    public function testUnknownOrForeignContextIsRejected(): void
    {
        $user = UserFactory::createOne();
        $project = ProjectFactory::createOne(['user' => $user]);
        $foreignContext = ProjectContextFactory::new(['project' => ProjectFactory::new(['user' => $user])])->completed()->create();
        $product = ProductFactory::createOne();
        ProjectProductMatchFactory::createOne(['context' => $foreignContext, 'product' => $product]);

        $client = $this->authClient($this->tokenFor($user));
        $url = self::placementsUrl($project);

        $client->request('POST', $url, ['json' => [
            'contextId' => $foreignContext->getId()->toRfc4122(),
            'productId' => $product->getId()->toRfc4122(),
        ]]);
        self::assertResponseStatusCodeSame(422);

        $client->request('POST', $url, ['json' => [
            'contextId' => Uuid::v7()->toRfc4122(),
            'productId' => $product->getId()->toRfc4122(),
        ]]);
        self::assertResponseStatusCodeSame(422);
    }

    public function testUnmatchedProductIsRejected(): void
    {
        $user = UserFactory::createOne();
        $context = $this->contextFor($user);
        $unmatched = ProductFactory::createOne();

        $this->authClient($this->tokenFor($user))
            ->request('POST', self::placementsUrl($context->getProject()), [
                'json' => [
                    'contextId' => $context->getId()->toRfc4122(),
                    'productId' => $unmatched->getId()->toRfc4122(),
                ],
            ]);

        self::assertResponseStatusCodeSame(422);
        self::assertCount(0, $this->generationMessages());
    }

    public function testMalformedIdsAreRejected(): void
    {
        $user = UserFactory::createOne();
        $project = ProjectFactory::createOne(['user' => $user]);

        $client = $this->authClient($this->tokenFor($user));
        $url = self::placementsUrl($project);

        $client->request('POST', $url, ['json' => ['contextId' => 'not-a-uuid', 'productId' => Uuid::v7()->toRfc4122()]]);
        self::assertResponseStatusCodeSame(422);
        self::assertJsonContains(['violations' => [['propertyPath' => 'contextId']]]);

        $client->request('POST', $url, ['json' => ['contextId' => Uuid::v7()->toRfc4122(), 'productId' => 'not-a-uuid']]);
        self::assertResponseStatusCodeSame(422);
        self::assertJsonContains(['violations' => [['propertyPath' => 'productId']]]);

        $client->request('POST', $url, ['json' => []]);
        self::assertResponseStatusCodeSame(422);
    }

    public function testUnknownProjectReturns404(): void
    {
        $client = $this->authClient($this->tokenFor(UserFactory::createOne()));
        $body = ['json' => ['contextId' => Uuid::v7()->toRfc4122(), 'productId' => Uuid::v7()->toRfc4122()]];

        $client->request('POST', '/api/projects/'.Uuid::v7()->toRfc4122().'/placements', $body);
        self::assertResponseStatusCodeSame(404);

        $client->request('POST', '/api/projects/not-a-uuid/placements', $body);
        self::assertResponseStatusCodeSame(404);
    }

    public function testOtherUsersProjectIsForbidden(): void
    {
        $stranger = UserFactory::createOne();
        $project = ProjectFactory::createOne();

        $this->authClient($this->tokenFor($stranger))
            ->request('POST', self::placementsUrl($project), [
                'json' => ['contextId' => Uuid::v7()->toRfc4122(), 'productId' => Uuid::v7()->toRfc4122()],
            ]);

        self::assertResponseStatusCodeSame(403);
        self::assertCount(0, $this->generationMessages());
    }

    public function testRequiresAuthentication(): void
    {
        $project = ProjectFactory::createOne();

        static::createClient()->request('POST', self::placementsUrl($project), [
            'json' => ['contextId' => Uuid::v7()->toRfc4122(), 'productId' => Uuid::v7()->toRfc4122()],
        ]);

        self::assertResponseStatusCodeSame(401);
    }

    private function contextFor(User $user, ?string $prompt = null): ProjectContext
    {
        $attributes = ['project' => ProjectFactory::new(['user' => $user])];

        if (null !== $prompt) {
            $attributes['prompt'] = $prompt;
        }

        return ProjectContextFactory::new($attributes)->completed()->create();
    }

    private static function placementsUrl(Project $project): string
    {
        return '/api/projects/'.$project->getId()->toRfc4122().'/placements';
    }

    /**
     * @return list<GeneratePlacementImage>
     */
    private function generationMessages(): array
    {
        $transport = static::getContainer()->get('messenger.transport.async_placements');
        \assert($transport instanceof InMemoryTransport);

        $messages = [];
        foreach ($transport->getSent() as $envelope) {
            $message = $envelope->getMessage();
            if ($message instanceof GeneratePlacementImage) {
                $messages[] = $message;
            }
        }

        return $messages;
    }
}
