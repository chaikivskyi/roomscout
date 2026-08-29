<?php

namespace App\Tests\Application\CatalogSearch;

use App\Catalog\Entity\Product;
use App\CatalogSearch\Command\MatchContextProducts;
use App\CatalogSearch\Command\MatchContextProductsHandler;
use App\CatalogSearch\Entity\ProductEmbedding;
use App\CatalogSearch\Entity\ProjectContextEmbedding;
use App\CatalogSearch\Entity\ProjectProductMatch;
use App\Project\Entity\ProjectContext;
use App\Project\Enum\ProjectContextStatus;
use App\Tests\Application\ApiTestCase;
use App\Tests\Factory\ProductFactory;
use App\Tests\Factory\ProjectContextFactory;
use App\Tests\Factory\ProjectImageVersionFactory;
use League\Flysystem\FilesystemOperator;
use Pgvector\Vector;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;
use Symfony\Component\Messenger\Exception\RecoverableMessageHandlingException;
use Symfony\Component\Messenger\Exception\UnrecoverableMessageHandlingException;
use Symfony\Component\Uid\Uuid;

final class MatchContextProductsHandlerTest extends ApiTestCase
{
    private const string PNG_1X1 = 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==';

    protected function tearDown(): void
    {
        $storage = $this->projectStorage();
        foreach ($storage->listContents('')->toArray() as $item) {
            $item->isDir() ? $storage->deleteDirectory($item->path()) : $storage->delete($item->path());
        }

        parent::tearDown();
    }

    public function testEmbedsThePromptWithTheImageAndStoresMatches(): void
    {
        $requests = [];
        $client = $this->mockCohere(function (string $method, string $url, array $options) use (&$requests): MockResponse {
            $requests[] = ['method' => $method, 'url' => $url, 'body' => $options['body'] ?? ''];

            return self::embeddingResponse([1.0, 0.0]);
        });

        $context = $this->contextWithImage('a walnut coffee table');
        $near = $this->embeddedProduct([1.0, 0.0]);
        $far = $this->embeddedProduct([-1.0, 0.0]);

        $this->handler()(new MatchContextProducts($context->getId()->toRfc4122()));

        self::assertSame(ProjectContextStatus::Completed, $context->getStatus());

        $matches = $this->matchesFor($context);
        self::assertCount(1, $matches);
        self::assertTrue($near->getId()->equals($matches[0]->getProduct()->getId()));
        self::assertSame([], $this->entityManager()->getRepository(ProjectProductMatch::class)
            ->findBy(['product' => $far->getId()]));

        $embedding = $this->entityManager()->getRepository(ProjectContextEmbedding::class)
            ->findOneBy(['context' => $context->getId()]);
        self::assertNotNull($embedding);

        self::assertSame(1, $client->getRequestsCount());
        self::assertSame('https://api.cohere.com/v2/embed', $requests[0]['url']);
        $body = self::decodeRequest($requests[0]['body']);
        self::assertSame('search_query', $body['input_type'] ?? null, 'The context query must be embedded as a query, not a document.');
        self::assertSame('a walnut coffee table', self::sentText($body), 'The prompt and the image go to Cohere as ONE composed input.');
        self::assertStringStartsWith('data:image/', self::sentImageUrl($body));
    }

    public function testZeroMatchesStillCompletesTheContext(): void
    {
        $this->mockCohere([self::embeddingResponse([1.0, 0.0])]);

        $context = $this->contextWithImage();
        $this->embeddedProduct([-1.0, 0.0]);

        $this->handler()(new MatchContextProducts($context->getId()->toRfc4122()));

        self::assertSame(ProjectContextStatus::Completed, $context->getStatus());
        self::assertCount(0, $this->matchesFor($context));
    }

    public function testAlreadyMatchedContextIsCompletedWithoutCallingCohere(): void
    {
        $client = $this->mockCohere([]);

        $context = $this->contextWithImage();
        $product = ProductFactory::createOne();
        $this->entityManager()->persist(new ProjectProductMatch(
            context: $context,
            product: $product,
            matchScore: 0.9,
            model: 'test-model',
            matchedAt: new \DateTimeImmutable(),
        ));
        $this->entityManager()->flush();

        $this->handler()(new MatchContextProducts($context->getId()->toRfc4122()));

        self::assertSame(ProjectContextStatus::Completed, $context->getStatus());
        self::assertSame(0, $client->getRequestsCount());
        self::assertCount(1, $this->matchesFor($context));
    }

    public function testMissingProjectImageMarksTheContextFailed(): void
    {
        $client = $this->mockCohere([]);

        $context = ProjectContextFactory::createOne();

        $this->handler()(new MatchContextProducts($context->getId()->toRfc4122()));

        self::assertSame(ProjectContextStatus::Failed, $context->getStatus());
        self::assertSame(0, $client->getRequestsCount());
    }

    public function testImageVersionWhoseFileIsGoneMarksTheContextFailed(): void
    {
        $client = $this->mockCohere([]);

        $context = ProjectContextFactory::createOne();
        ProjectImageVersionFactory::createOne(['project' => $context->getProject(), 'imagePath' => 'gone/image.png']);

        $this->handler()(new MatchContextProducts($context->getId()->toRfc4122()));

        self::assertSame(ProjectContextStatus::Failed, $context->getStatus());
        self::assertSame(0, $client->getRequestsCount());
    }

    public function testDeletedContextIsSkippedWithoutRetries(): void
    {
        $client = $this->mockCohere([]);

        $this->handler()(new MatchContextProducts(Uuid::v7()->toRfc4122()));

        self::assertSame(0, $client->getRequestsCount());
    }

    public function testMalformedContextIdIsNotRetried(): void
    {
        $this->mockCohere([]);

        $this->expectException(UnrecoverableMessageHandlingException::class);
        $this->expectExceptionMessage('999999');
        $this->handler()(new MatchContextProducts('999999'));
    }

    public function testRateLimitHonorsRetryAfterAndLeavesTheContextProcessing(): void
    {
        $this->mockCohere([new MockResponse('slow down', ['http_code' => 429, 'response_headers' => ['retry-after' => '7']])]);

        $context = $this->contextWithImage();

        try {
            $this->handler()(new MatchContextProducts($context->getId()->toRfc4122()));
            self::fail('Expected a recoverable exception.');
        } catch (RecoverableMessageHandlingException $e) {
            self::assertSame(7000, $e->getRetryDelay());
            self::assertFalse($e->forceRetry(), 'Retries must stay bounded by the transport retry strategy.');
        }

        self::assertSame(ProjectContextStatus::Processing, $context->getStatus());
    }

    public function testClientErrorIsUnrecoverable(): void
    {
        $this->mockCohere([new MockResponse('invalid request', ['http_code' => 400])]);

        $context = $this->contextWithImage();

        $this->expectException(UnrecoverableMessageHandlingException::class);
        $this->handler()(new MatchContextProducts($context->getId()->toRfc4122()));
    }

    private function contextWithImage(?string $prompt = null): ProjectContext
    {
        $context = ProjectContextFactory::createOne(null !== $prompt ? ['prompt' => $prompt] : []);
        $path = Uuid::v7()->toRfc4122().'/image.png';
        $this->projectStorage()->write($path, base64_decode(self::PNG_1X1));
        ProjectImageVersionFactory::createOne(['project' => $context->getProject(), 'imagePath' => $path]);

        return $context;
    }

    /**
     * @param list<float> $vector
     */
    private function embeddedProduct(array $vector): Product
    {
        $product = ProductFactory::createOne();

        $this->entityManager()->persist(new ProductEmbedding(
            product: $product,
            embedding: new Vector(self::pad($vector)),
            model: 'test-model',
            sourceThumbnailHash: str_repeat('a', 64),
            embeddedAt: new \DateTimeImmutable(),
        ));
        $this->entityManager()->flush();

        return $product;
    }

    /**
     * @return list<ProjectProductMatch>
     */
    private function matchesFor(ProjectContext $context): array
    {
        return $this->entityManager()->getRepository(ProjectProductMatch::class)
            ->findBy(['context' => $context->getId()]);
    }

    /**
     * @param list<float> $vector
     */
    private static function embeddingResponse(array $vector): MockResponse
    {
        return new MockResponse(json_encode(['embeddings' => ['float' => [self::pad($vector)]]], JSON_THROW_ON_ERROR));
    }

    /**
     * @param list<float> $vector
     *
     * @return list<float>
     */
    private static function pad(array $vector): array
    {
        return array_merge($vector, array_fill(0, ProductEmbedding::DIMENSIONS - \count($vector), 0.0));
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

    private function handler(): MatchContextProductsHandler
    {
        return static::getContainer()->get(MatchContextProductsHandler::class);
    }

    private function projectStorage(): FilesystemOperator
    {
        return static::getContainer()->get('project.storage');
    }

    /**
     * @return array{input_type?: string, inputs?: list<array{content: list<array{text?: string, image_url?: array{url: string}}>}>}
     */
    private static function decodeRequest(mixed $body): array
    {
        self::assertIsString($body);
        /** @var array{input_type?: string, inputs?: list<array{content: list<array{text?: string, image_url?: array{url: string}}>}>} $decoded */
        $decoded = json_decode($body, true);

        return $decoded;
    }

    /**
     * @param array{inputs?: list<array{content: list<array{text?: string, image_url?: array{url: string}}>}>} $request
     */
    private static function sentText(array $request): string
    {
        foreach ($request['inputs'][0]['content'] ?? [] as $part) {
            if (isset($part['text'])) {
                return $part['text'];
            }
        }

        self::fail('No text part in the captured request.');
    }

    /**
     * @param array{inputs?: list<array{content: list<array{text?: string, image_url?: array{url: string}}>}>} $request
     */
    private static function sentImageUrl(array $request): string
    {
        foreach ($request['inputs'][0]['content'] ?? [] as $part) {
            if (isset($part['image_url'])) {
                return $part['image_url']['url'];
            }
        }

        self::fail('No image url in the captured request.');
    }
}
