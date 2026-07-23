<?php

namespace App\CatalogSearch\Entity;

use App\Catalog\Entity\Product;
use App\CatalogSearch\Repository\ProductEmbeddingRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Pgvector\Vector;

#[ORM\Entity(repositoryClass: ProductEmbeddingRepository::class)]
#[ORM\Index(name: 'idx_product_embedding_hnsw', columns: ['embedding'])]
class ProductEmbedding
{
    public const DIMENSIONS = 1536;

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\OneToOne(targetEntity: Product::class)]
    #[ORM\JoinColumn(name: 'product_id', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
    private Product $product;

    #[ORM\Column(type: 'vector')]
    private Vector $embedding;

    #[ORM\Column(length: 64)]
    private string $model;

    #[ORM\Column(length: 64)]
    private string $sourceThumbnailHash;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $embeddedAt;

    public function __construct(
        Product $product,
        Vector $embedding,
        string $model,
        string $sourceThumbnailHash,
        \DateTimeImmutable $embeddedAt,
    ) {
        $this->product = $product;
        $this->embedding = $embedding;
        $this->model = $model;
        $this->sourceThumbnailHash = $sourceThumbnailHash;
        $this->embeddedAt = $embeddedAt;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getProduct(): Product
    {
        return $this->product;
    }

    public function getEmbedding(): Vector
    {
        return $this->embedding;
    }

    public function getModel(): string
    {
        return $this->model;
    }

    public function getSourceThumbnailHash(): string
    {
        return $this->sourceThumbnailHash;
    }

    public function getEmbeddedAt(): \DateTimeImmutable
    {
        return $this->embeddedAt;
    }
}
