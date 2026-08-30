<?php

namespace App\Tests\Application\CatalogSearch;

use App\Catalog\Entity\Product;
use App\CatalogSearch\Command\MatchContextProducts;
use App\CatalogSearch\Command\MatchContextProductsHandler;
use App\CatalogSearch\Entity\ProductEmbedding;
use App\CatalogSearch\Entity\ProjectContextEmbedding;
use App\CatalogSearch\Entity\ProjectProductMatch;
use App\CatalogSearch\Exception\EmbeddingRateLimitedException;
use App\CatalogSearch\Exception\EmbeddingRejectedException;
use App\Project\Entity\ProjectContext;
use App\Project\Enum\ProjectContextStatus;
use App\Tests\Application\ApiTestCase;
use App\Tests\Factory\ProductFactory;
use App\Tests\Factory\ProjectContextFactory;
use App\Tests\Factory\ProjectImageVersionFactory;
use App\Tests\Fake\FakeImageEmbedder;
use League\Flysystem\FilesystemOperator;
use Pgvector\Vector;
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
        $embedder = $this->embedder()->willReturn([1.0, 0.0]);

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

        $calls = $embedder->calls();
        self::assertCount(1, $calls);
        self::assertSame('query', $calls[0]['type'], 'The context must be embedded as a query, not a document.');
        self::assertSame('a walnut coffee table', $calls[0]['prompt'], 'The prompt and the image go to the embedder as ONE composed input.');
        self::assertSame(base64_decode(self::PNG_1X1), $calls[0]['bytes']);
    }

    public function testZeroMatchesStillCompletesTheContext(): void
    {
        $this->embedder()->willReturn([1.0, 0.0]);

        $context = $this->contextWithImage();
        $this->embeddedProduct([-1.0, 0.0]);

        $this->handler()(new MatchContextProducts($context->getId()->toRfc4122()));

        self::assertSame(ProjectContextStatus::Completed, $context->getStatus());
        self::assertCount(0, $this->matchesFor($context));
    }

    public function testAlreadyMatchedContextIsCompletedWithoutEmbedding(): void
    {
        $embedder = $this->embedder();

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
        self::assertSame(0, $embedder->callCount());
        self::assertCount(1, $this->matchesFor($context));
    }

    public function testMissingProjectImageMarksTheContextFailed(): void
    {
        $embedder = $this->embedder();

        $context = ProjectContextFactory::createOne();

        $this->handler()(new MatchContextProducts($context->getId()->toRfc4122()));

        self::assertSame(ProjectContextStatus::Failed, $context->getStatus());
        self::assertSame(0, $embedder->callCount());
    }

    public function testImageVersionWhoseFileIsGoneMarksTheContextFailed(): void
    {
        $embedder = $this->embedder();

        $context = ProjectContextFactory::createOne();
        ProjectImageVersionFactory::createOne(['project' => $context->getProject(), 'imagePath' => 'gone/image.png']);

        $this->handler()(new MatchContextProducts($context->getId()->toRfc4122()));

        self::assertSame(ProjectContextStatus::Failed, $context->getStatus());
        self::assertSame(0, $embedder->callCount());
    }

    public function testDeletedContextIsSkippedWithoutRetries(): void
    {
        $embedder = $this->embedder();

        $this->handler()(new MatchContextProducts(Uuid::v7()->toRfc4122()));

        self::assertSame(0, $embedder->callCount());
    }

    public function testMalformedContextIdIsNotRetried(): void
    {
        $this->expectException(UnrecoverableMessageHandlingException::class);
        $this->expectExceptionMessage('999999');
        $this->handler()(new MatchContextProducts('999999'));
    }

    public function testRateLimitHonorsRetryAfterAndLeavesTheContextProcessing(): void
    {
        $this->embedder()->willThrow(new EmbeddingRateLimitedException('slow down', 7000));

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

    public function testRejectedEmbeddingIsUnrecoverable(): void
    {
        $this->embedder()->willThrow(new EmbeddingRejectedException('invalid request'));

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
            embedding: new Vector(FakeImageEmbedder::pad($vector)),
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

    private function embedder(): FakeImageEmbedder
    {
        return static::getContainer()->get(FakeImageEmbedder::class);
    }

    private function handler(): MatchContextProductsHandler
    {
        return static::getContainer()->get(MatchContextProductsHandler::class);
    }

    private function projectStorage(): FilesystemOperator
    {
        return static::getContainer()->get('project.storage');
    }
}
