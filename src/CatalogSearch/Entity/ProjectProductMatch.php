<?php

namespace App\CatalogSearch\Entity;

use App\Catalog\Entity\Product;
use App\CatalogSearch\Repository\ProjectProductMatchRepository;
use App\Project\Entity\Project;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

/**
 * A catalog product matched to a project's image+prompt query. Immutable
 * record owned by CatalogSearch; rows cascade away with either side.
 */
#[ORM\Entity(repositoryClass: ProjectProductMatchRepository::class)]
#[ORM\UniqueConstraint(name: 'uniq_project_product_match', columns: ['project_id', 'product_id'])]
class ProjectProductMatch
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Project::class)]
    #[ORM\JoinColumn(name: 'project_id', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
    private Project $project;

    #[ORM\ManyToOne(targetEntity: Product::class)]
    #[ORM\JoinColumn(name: 'product_id', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
    private Product $product;

    /** Cosine similarity (1 - cosine distance) between query and product embedding. */
    #[ORM\Column]
    private float $matchScore;

    /**
     * Embedding model the match was computed with; matches from an older
     * model are stale once the model is upgraded.
     */
    #[ORM\Column(length: 64)]
    private string $model;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $matchedAt;

    public function __construct(
        Project $project,
        Product $product,
        float $matchScore,
        string $model,
        \DateTimeImmutable $matchedAt,
    ) {
        $this->project = $project;
        $this->product = $product;
        $this->matchScore = $matchScore;
        $this->model = $model;
        $this->matchedAt = $matchedAt;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getProject(): Project
    {
        return $this->project;
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
