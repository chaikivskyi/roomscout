<?php

namespace App\Tests\Application\Visualization;

use App\Identity\Entity\User;
use App\Visualization\Command\GenerateVisualizationImage;
use App\Visualization\Entity\ProductVisualization;
use App\Visualization\Enum\VisualizationStatus;
use App\Project\Entity\ProjectContext;
use App\Tests\Application\ApiTestCase;
use App\Tests\Factory\ProductFactory;
use App\Tests\Factory\ProductVisualizationFactory;
use App\Tests\Factory\ProjectContextFactory;
use App\Tests\Factory\ProjectFactory;
use App\Tests\Factory\ProjectProductMatchFactory;
use App\Tests\Factory\UserFactory;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Symfony\Component\Messenger\Transport\InMemory\InMemoryTransport;
use Symfony\Component\Uid\Uuid;

final class CreateVisualizationTest extends ApiTestCase
{
    private const string VISUALIZATIONS_URL = '/api/visualizations';

    public function testCreatesVisualizationForMatchedProduct(): void
    {
        $user = UserFactory::createOne();
        $context = $this->contextFor($user, 'a walnut table under the window');
        $product = ProductFactory::createOne();
        ProjectProductMatchFactory::createOne(['context' => $context, 'product' => $product]);

        $response = $this->authClient($this->tokenFor($user))
            ->request('POST', self::VISUALIZATIONS_URL, [
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
            'The visualization payload must expose exactly these fields.',
        );

        self::assertIsString($data['id']);
        $visualization = $this->entityManager()->getRepository(ProductVisualization::class)
            ->find(Uuid::fromString($data['id']));
        self::assertNotNull($visualization);
        self::assertSame(VisualizationStatus::Processing, $visualization->getStatus());
        self::assertSame('a walnut table under the window', $visualization->getPrompt());

        $messages = $this->generationMessages();
        self::assertCount(1, $messages);
        self::assertSame($data['id'], $messages[0]->visualizationId);
    }

    public function testRejectsWhileAnotherVisualizationOfTheProjectIsProcessing(): void
    {
        $user = UserFactory::createOne();
        $context = $this->contextFor($user);
        $product = ProductFactory::createOne();
        ProjectProductMatchFactory::createOne(['context' => $context, 'product' => $product]);

        $sibling = ProjectContextFactory::new(['project' => $context->getProject()])->completed()->create();
        ProductVisualizationFactory::createOne(['context' => $sibling]);

        $this->authClient($this->tokenFor($user))
            ->request('POST', self::VISUALIZATIONS_URL, [
                'json' => [
                    'contextId' => $context->getId()->toRfc4122(),
                    'productId' => $product->getId()->toRfc4122(),
                ],
            ]);

        self::assertResponseStatusCodeSame(409);
    }

    public function testTerminalVisualizationsDoNotBlockANewOne(): void
    {
        $user = UserFactory::createOne();
        $context = $this->contextFor($user);
        $product = ProductFactory::createOne();
        ProjectProductMatchFactory::createOne(['context' => $context, 'product' => $product]);

        ProductVisualizationFactory::new(['context' => $context])->completed()->create();
        ProductVisualizationFactory::new(['context' => $context])->failed()->create();

        $this->authClient($this->tokenFor($user))
            ->request('POST', self::VISUALIZATIONS_URL, [
                'json' => [
                    'contextId' => $context->getId()->toRfc4122(),
                    'productId' => $product->getId()->toRfc4122(),
                ],
            ]);

        self::assertResponseStatusCodeSame(201);
    }

    public function testOnlyOneProcessingVisualizationPerProjectAtTheDatabaseLevel(): void
    {
        $visualization = ProductVisualizationFactory::createOne();
        $sibling = ProjectContextFactory::new(['project' => $visualization->getProject()])->completed()->create();
        $product = ProductFactory::createOne();

        $entityManager = $this->entityManager();
        $entityManager->persist(new ProductVisualization($visualization->getProject(), $sibling, $product, 'gemini-test-image'));

        $this->expectException(UniqueConstraintViolationException::class);
        $entityManager->flush();
    }

    public function testUnknownContextIsRejected(): void
    {
        $user = UserFactory::createOne();
        $product = ProductFactory::createOne();

        $this->authClient($this->tokenFor($user))
            ->request('POST', self::VISUALIZATIONS_URL, [
                'json' => [
                    'contextId' => Uuid::v7()->toRfc4122(),
                    'productId' => $product->getId()->toRfc4122(),
                ],
            ]);

        self::assertResponseStatusCodeSame(422);
        self::assertCount(0, $this->generationMessages());
    }

    public function testAnyOwnedContextIsAcceptedRegardlessOfProject(): void
    {
        $user = UserFactory::createOne();
        ProjectFactory::createOne(['user' => $user]);
        $context = $this->contextFor($user);
        $product = ProductFactory::createOne();
        ProjectProductMatchFactory::createOne(['context' => $context, 'product' => $product]);

        $this->authClient($this->tokenFor($user))
            ->request('POST', self::VISUALIZATIONS_URL, [
                'json' => [
                    'contextId' => $context->getId()->toRfc4122(),
                    'productId' => $product->getId()->toRfc4122(),
                ],
            ]);

        self::assertResponseStatusCodeSame(201);
        self::assertJsonContains([
            'contextId' => $context->getId()->toRfc4122(),
            'productId' => $product->getId()->toRfc4122(),
        ]);
    }

    public function testUnmatchedProductIsRejected(): void
    {
        $user = UserFactory::createOne();
        $context = $this->contextFor($user);
        $unmatched = ProductFactory::createOne();

        $this->authClient($this->tokenFor($user))
            ->request('POST', self::VISUALIZATIONS_URL, [
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

        $client = $this->authClient($this->tokenFor($user));
        $url = self::VISUALIZATIONS_URL;

        $client->request('POST', $url, ['json' => ['contextId' => 'not-a-uuid', 'productId' => Uuid::v7()->toRfc4122()]]);
        self::assertResponseStatusCodeSame(422);
        self::assertJsonContains(['violations' => [['propertyPath' => 'contextId']]]);

        $client->request('POST', $url, ['json' => ['contextId' => Uuid::v7()->toRfc4122(), 'productId' => 'not-a-uuid']]);
        self::assertResponseStatusCodeSame(422);
        self::assertJsonContains(['violations' => [['propertyPath' => 'productId']]]);

        $client->request('POST', $url, ['json' => []]);
        self::assertResponseStatusCodeSame(422);
    }

    public function testNonDeserializableBodyReturns400(): void
    {
        $user = UserFactory::createOne();

        $this->authClient($this->tokenFor($user))
            ->request('POST', self::VISUALIZATIONS_URL, [
                'headers' => ['Content-Type' => 'application/json'],
                'body' => 'not json at all',
            ]);

        self::assertResponseStatusCodeSame(400);
    }

    public function testOtherUsersContextIsForbidden(): void
    {
        $stranger = UserFactory::createOne();
        $context = $this->contextFor(UserFactory::createOne());
        $product = ProductFactory::createOne();
        ProjectProductMatchFactory::createOne(['context' => $context, 'product' => $product]);

        $this->authClient($this->tokenFor($stranger))
            ->request('POST', self::VISUALIZATIONS_URL, [
                'json' => [
                    'contextId' => $context->getId()->toRfc4122(),
                    'productId' => $product->getId()->toRfc4122(),
                ],
            ]);

        self::assertResponseStatusCodeSame(403);
        self::assertCount(0, $this->generationMessages());
    }

    public function testRequiresAuthentication(): void
    {
        static::createClient()->request('POST', self::VISUALIZATIONS_URL, [
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

    /**
     * @return list<GenerateVisualizationImage>
     */
    private function generationMessages(): array
    {
        $transport = static::getContainer()->get('messenger.transport.async_visualizations');
        \assert($transport instanceof InMemoryTransport);

        $messages = [];
        foreach ($transport->getSent() as $envelope) {
            $message = $envelope->getMessage();
            if ($message instanceof GenerateVisualizationImage) {
                $messages[] = $message;
            }
        }

        return $messages;
    }
}
