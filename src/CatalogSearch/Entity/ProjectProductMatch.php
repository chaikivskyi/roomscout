<?php

namespace App\CatalogSearch\Entity;

use App\Catalog\Entity\Product;
use App\CatalogSearch\Repository\ProjectProductMatchRepository;
use App\Project\Entity\ProjectContext;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: ProjectProductMatchRepository::class)]
#[ORM\UniqueConstraint(name: 'uniq_context_product_match', columns: ['context_id', 'product_id'])]
class ProjectProductMatch
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: ProjectContext::class)]
    #[ORM\JoinColumn(name: 'context_id', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
    private ProjectContext $context;

    #[ORM\ManyToOne(targetEntity: Product::class)]
    #[ORM\JoinColumn(name: 'product_id', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
    private Product $product;

    #[ORM\Column]
    private float $matchScore;

    #[ORM\Column(length: 64)]
    private string $model;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $matchedAt;

    public function __construct(
        ProjectContext $context,
        Product $product,
        float $matchScore,
        string $model,
        \DateTimeImmutable $matchedAt,
    ) {
        $this->context = $context;
        $this->product = $product;
        $this->matchScore = $matchScore;
        $this->model = $model;
        $this->matchedAt = $matchedAt;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getContext(): ProjectContext
    {
        return $this->context;
    }

    public function getProduct(): Product
    {
        return $this->product;
    }

    public function getMatchScore(): float
    {
        return $this->matchScore;
    }

    public function getModel(): string
    {
        return $this->model;
    }

    public function getMatchedAt(): \DateTimeImmutable
    {
        return $this->matchedAt;
    }
}
