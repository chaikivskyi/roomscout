<?php

namespace App\Tests\Application\CatalogSearch;

use App\Catalog\Entity\Product;
use App\CatalogSearch\Entity\ProductEmbedding;
use App\CatalogSearch\Entity\ProjectEmbedding;
use App\CatalogSearch\Entity\ProjectProductMatch;
use App\CatalogSearch\Message\MatchProjectProductsMessage;
use App\CatalogSearch\MessageHandler\MatchProjectProductsHandler;
use App\Project\Entity\Project;
use App\Project\Enum\ProjectStatus;
use App\Tests\Application\ApiTestCase;
use App\Tests\Factory\ProductFactory;
use App\Tests\Factory\ProjectFactory;
use League\Flysystem\FilesystemOperator;
use Pgvector\Vector;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;
use Symfony\Component\Messenger\Exception\RecoverableMessageHandlingException;
use Symfony\Component\Messenger\Exception\UnrecoverableMessageHandlingException;
use Symfony\Component\Uid\Uuid;

final class MatchProjectProductsHandlerTest extends ApiTestCase
{
    private const string PNG_1X1 = 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==';

    protected function tearDown(): void
    {
        $storage = $this->storage();
        foreach ($storage->listContents('')->toArray() as $item) {
            $item->isDir() ? $storage->deleteDirectory($item->path()) : $storage->delete($item->path());
        }

        parent::tearDown();
    }

    public function testStoresMatchesAboveThresholdWithScores(): void
    {
        // Query vector is the first axis; similarities are then simply the
        // first component of each (unit-length) product embedding.
        $requests = [];
        $this->mockCohere(function (string $method, string $url, array $options) use (&$requests): MockResponse {
            $requests[] = ['method' => $method, 'url' => $url, 'body' => $options['body'] ?? ''];

            return self::queryResponse(self::vector(1.0));
        });

        $identical = $this->seedEmbeddedProduct(self::vector(1.0));            // similarity 1.0 -> stored
        $close = $this->seedEmbeddedProduct(self::vector(0.6, 0.8));           // similarity 0.6 -> stored
        $far = $this->seedEmbeddedProduct(self::vector(0.4, 0.9165151));      // similarity 0.4 -> filtered
        $orthogonal = $this->seedEmbeddedProduct(self::vector(0.0, 1.0));      // similarity 0.0 -> filtered

        $project = $this->projectWithImage('match/image.png', 'cozy beige sofa');
        self::assertSame(ProjectStatus::Processing, $project->getStatus());

        $this->handler()(new MatchProjectProductsMessage($project->getId()->toRfc4122()));

        self::assertSame(ProjectStatus::Completed, $project->getStatus());

        $projectEmbedding = $this->entityManager()->getRepository(ProjectEmbedding::class)
            ->findOneBy(['project' => $project->getId()]);
        self::assertNotNull($projectEmbedding);
        self::assertSame('embed-v4.0', $projectEmbedding->getModel());

        $matches = $this->findMatches($project);
        self::assertCount(2, $matches);

        // Ordered by distance: identical first.
        self::assertSame($identical->getId(), $matches[0]->getProduct()->getId());
        self::assertEqualsWithDelta(1.0, $matches[0]->getMatchScore(), 0.001);
        self::assertSame($close->getId(), $matches[1]->getProduct()->getId());
        self::assertEqualsWithDelta(0.6, $matches[1]->getMatchScore(), 0.001);
        self::assertSame('embed-v4.0', $matches[0]->getModel());

        self::assertCount(1, $requests);
        self::assertSame('https://api.cohere.com/v2/embed', $requests[0]['url']);
        $body = self::decodeRequest($requests[0]['body']);
        self::assertSame('search_query', $body['input_type']);
        self::assertSame(['type' => 'text', 'text' => 'cozy beige sofa'], $body['inputs'][0]['content'][0]);
        self::assertStringStartsWith('data:image/png;base64,', $body['inputs'][0]['content'][1]['image_url']['url']);
    }

    public function testSecondInvocationIsIdempotent(): void
    {
        $client = $this->mockCohere([self::queryResponse(self::vector(1.0))]);
        $this->seedEmbeddedProduct(self::vector(1.0));
        $project = $this->projectWithImage('idempotent/image.png', 'a lamp');

        $handler = $this->handler();
        $handler(new MatchProjectProductsMessage($project->getId()->toRfc4122()));
        $handler(new MatchProjectProductsMessage($project->getId()->toRfc4122()));

        self::assertSame(1, $client->getRequestsCount());
        self::assertCount(1, $this->findMatches($project));
        self::assertSame(ProjectStatus::Completed, $project->getStatus());
    }

    public function testStoredEmbeddingIsReusedInsteadOfRecomputed(): void
    {
        // Exactly ONE mock response: a second Cohere call would throw.
        $client = $this->mockCohere([self::queryResponse(self::vector(1.0))]);
        $this->seedEmbeddedProduct(self::vector(1.0));
        $project = $this->projectWithImage('reuse/image.png', 'a sofa');

        $handler = $this->handler();
        $handler(new MatchProjectProductsMessage($project->getId()->toRfc4122()));

        foreach ($this->findMatches($project) as $match) {
            $this->entityManager()->remove($match);
        }
        $this->entityManager()->flush();

        $handler(new MatchProjectProductsMessage($project->getId()->toRfc4122()));

        self::assertSame(1, $client->getRequestsCount());
        self::assertCount(1, $this->findMatches($project));
    }

    public function testMissingProjectIsRetriedWithBoundedRetries(): void
    {
        $client = $this->mockCohere([]);

        try {
            $this->handler()(new MatchProjectProductsMessage(Uuid::v7()->toRfc4122()));
            self::fail('Expected a recoverable exception.');
        } catch (RecoverableMessageHandlingException $e) {
            self::assertFalse($e->forceRetry());
        }

        self::assertSame(0, $client->getRequestsCount());
    }

    public function testSkipsWhenProjectImageIsMissingFromStorage(): void
    {
        $client = $this->mockCohere([]);
        $project = ProjectFactory::createOne(['imagePath' => 'missing/image.png']);

        $this->handler()(new MatchProjectProductsMessage($project->getId()->toRfc4122()));

        self::assertSame(0, $client->getRequestsCount());
        self::assertCount(0, $this->findMatches($project));
        // Terminal skip must not leave the project stuck in "processing".
        self::assertSame(ProjectStatus::Failed, $project->getStatus());
    }

    public function testFailedProjectCompletesWhenRetriedMatchingSucceeds(): void
    {
        $this->mockCohere([self::queryResponse(self::vector(1.0))]);
        $this->seedEmbeddedProduct(self::vector(1.0));
        $project = $this->projectWithImage('retry/image.png', 'a table');
        $project->markFailed();
        $this->entityManager()->flush();

        $this->handler()(new MatchProjectProductsMessage($project->getId()->toRfc4122()));

        self::assertSame(ProjectStatus::Completed, $project->getStatus());
        self::assertCount(1, $this->findMatches($project));
    }

    public function testCohereRejectionIsTranslatedToUnrecoverable(): void
    {
        $this->mockCohere([new MockResponse('unsupported image', ['http_code' => 400])]);
        $project = $this->projectWithImage('rejected/image.png', 'a chair');

        $this->expectException(UnrecoverableMessageHandlingException::class);
        $this->expectExceptionMessage('unsupported image');
        $this->handler()(new MatchProjectProductsMessage($project->getId()->toRfc4122()));
    }

    public function testStoresNothingWhenNoProductClearsTheThreshold(): void
    {
        $client = $this->mockCohere([self::queryResponse(self::vector(1.0))]);
        $this->seedEmbeddedProduct(self::vector(0.0, 1.0));
        $project = $this->projectWithImage('nomatch/image.png', 'a rug');

        $this->handler()(new MatchProjectProductsMessage($project->getId()->toRfc4122()));

        self::assertSame(1, $client->getRequestsCount());
        self::assertCount(0, $this->findMatches($project));
        self::assertSame(ProjectStatus::Completed, $project->getStatus());
    }

    /**
     * @return list<float>
     */
    private static function vector(float ...$components): array
    {
        return array_pad(array_values($components), ProductEmbedding::DIMENSIONS, 0.0);
    }

    /**
     * @param list<float> $vector
     */
    private static function queryResponse(array $vector): MockResponse
    {
        return new MockResponse(json_encode(['embeddings' => ['float' => [$vector]]], JSON_THROW_ON_ERROR));
    }

    /**
     * @return array{input_type: string, inputs: list<array{content: array{0: array{type: string, text: string}, 1: array{image_url: array{url: string}}}}>}
     */
    private static function decodeRequest(mixed $body): array
    {
        self::assertIsString($body);
        /** @var array{input_type: string, inputs: list<array{content: array{0: array{type: string, text: string}, 1: array{image_url: array{url: string}}}}>} $decoded */
        $decoded = json_decode($body, true);

        return $decoded;
    }

    /**
     * @param list<float> $embedding
     */
    private function seedEmbeddedProduct(array $embedding): Product
    {
        $product = ProductFactory::createOne();
        $this->entityManager()->persist(new ProductEmbedding(
            $product,
            new Vector($embedding),
            'embed-v4.0',
            hash('sha256', 'seed'),
            new \DateTimeImmutable(),
        ));
        $this->entityManager()->flush();

        return $product;
    }

    private function projectWithImage(string $imagePath, string $prompt): Project
    {
        $project = ProjectFactory::createOne(['imagePath' => $imagePath, 'prompt' => $prompt]);
        $this->storage()->write($imagePath, base64_decode(self::PNG_1X1));

        return $project;
    }

    /**
     * @return list<ProjectProductMatch>
     */
    private function findMatches(Project $project): array
    {
        return $this->entityManager()->getRepository(ProjectProductMatch::class)
            ->findBy(['project' => $project->getId()], ['matchScore' => 'DESC']);
    }

    /**
     * @param callable|list<MockResponse> $responseFactory
     */
    private function mockCohere(callable|array $responseFactory): MockHttpClient
    {
        $client = new MockHttpClient($responseFactory, 'https://api.cohere.com');
        static::getContainer()->set('cohere.client', $client);

        return $client;
    }

    private function handler(): MatchProjectProductsHandler
    {
        return static::getContainer()->get(MatchProjectProductsHandler::class);
    }

    private function storage(): FilesystemOperator
    {
        return static::getContainer()->get('project.storage');
    }
}
