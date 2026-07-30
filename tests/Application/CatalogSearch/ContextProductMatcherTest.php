<?php

namespace App\Tests\Application\CatalogSearch;

use App\Catalog\Entity\Product;
use App\CatalogSearch\Entity\ProductEmbedding;
use App\CatalogSearch\Entity\ProjectContextEmbedding;
use App\CatalogSearch\Entity\ProjectProductMatch;
use App\CatalogSearch\Service\ContextProductMatcher;
use App\Project\Entity\ProjectContext;
use App\Project\Enum\ProjectContextStatus;
use App\Tests\Application\ApiTestCase;
use App\Tests\Factory\ProductFactory;
use App\Tests\Factory\ProjectContextFactory;
use Pgvector\Vector;
use Symfony\Component\Uid\Uuid;
use Symfony\Component\Uid\UuidV7;

/**
 * Covers ProjectProductMatchRepository::insertMatchesWithinCosineDistance, the one write
 * path that bypasses the ORM entirely — it has to supply the primary key itself.
 */
final class ContextProductMatcherTest extends ApiTestCase
{
    public function testMatchInsertsRowsForProductsWithinDistance(): void
    {
        $context = ProjectContextFactory::createOne();
        $near = $this->embeddedProduct([1.0, 0.0]);
        $far = $this->embeddedProduct([-1.0, 0.0]);

        $inserted = $this->matcher()->match($context, $this->contextEmbedding($context, [1.0, 0.0]));

        self::assertSame(1, $inserted);

        $matches = $this->entityManager()->getRepository(ProjectProductMatch::class)
            ->findBy(['context' => $context->getId()]);

        self::assertCount(1, $matches);
        self::assertTrue($near->getId()->equals($matches[0]->getProduct()->getId()));
        self::assertEqualsWithDelta(1.0, $matches[0]->getMatchScore(), 0.0001);
        self::assertSame(ProjectContextStatus::Completed, $context->getStatus());

        self::assertSame([], $this->entityManager()->getRepository(ProjectProductMatch::class)
            ->findBy(['product' => $far->getId()]));
    }

    /**
     * These rows are inserted by raw SQL, not the ORM, so their ids come from Postgres'
     * uuidv7() — they must be UUIDv7 like every entity-assigned id, carrying a real
     * timestamp rather than the random bits gen_random_uuid() would produce.
     */
    public function testBulkInsertedIdsAreUuidV7(): void
    {
        $context = ProjectContextFactory::createOne();
        for ($i = 0; $i < 5; ++$i) {
            $this->embeddedProduct([1.0, 0.0]);
        }

        $this->matcher()->match($context, $this->contextEmbedding($context, [1.0, 0.0]));

        $ids = array_map(
            static fn (ProjectProductMatch $m): Uuid => $m->getId(),
            $this->entityManager()->getRepository(ProjectProductMatch::class)->findBy(['context' => $context->getId()]),
        );

        self::assertCount(5, $ids);
        self::assertCount(5, array_unique(array_map(static fn (Uuid $id): string => (string) $id, $ids)));

        foreach ($ids as $id) {
            self::assertInstanceOf(UuidV7::class, $id, 'Bulk-inserted ids must be UUIDv7, not v4.');
            self::assertEqualsWithDelta(time(), $id->getDateTime()->getTimestamp(), 120, 'The v7 time prefix must be a real timestamp.');
        }
    }

    public function testMatchStoresNothingWhenNoProductIsCloseEnough(): void
    {
        $context = ProjectContextFactory::createOne();
        $this->embeddedProduct([-1.0, 0.0]);

        $inserted = $this->matcher()->match($context, $this->contextEmbedding($context, [1.0, 0.0]));

        self::assertSame(0, $inserted);
        self::assertSame(ProjectContextStatus::Completed, $context->getStatus());
    }

    /**
     * @param list<float> $vector
     */
    private function embeddedProduct(array $vector): Product
    {
        $product = ProductFactory::createOne();

        $this->entityManager()->persist(new ProductEmbedding(
            product: $product,
            embedding: new Vector($this->pad($vector)),
            model: 'test-model',
            sourceThumbnailHash: str_repeat('a', 64),
            embeddedAt: new \DateTimeImmutable(),
        ));
        $this->entityManager()->flush();

        return $product;
    }

    /**
     * @param list<float> $vector
     */
    private function contextEmbedding(ProjectContext $context, array $vector): ProjectContextEmbedding
    {
        $embedding = new ProjectContextEmbedding(
            context: $context,
            embedding: new Vector($this->pad($vector)),
            model: 'test-model',
            embeddedAt: new \DateTimeImmutable(),
        );

        $this->entityManager()->persist($embedding);
        $this->entityManager()->flush();

        return $embedding;
    }

    /**
     * @param list<float> $vector
     *
     * @return list<float>
     */
    private function pad(array $vector): array
    {
        return array_merge($vector, array_fill(0, ProductEmbedding::DIMENSIONS - \count($vector), 0.0));
    }

    private function matcher(): ContextProductMatcher
    {
        return static::getContainer()->get(ContextProductMatcher::class);
    }
}
