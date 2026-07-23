<?php

namespace App\CatalogSearch\Entity;

use App\CatalogSearch\Repository\ProjectEmbeddingRepository;
use App\Project\Entity\Project;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Pgvector\Vector;

/**
 * The composed prompt+image query embedding of a project, computed once after
 * creation and reused by every matching run. Project inputs are immutable
 * (no update API), so the vector never goes stale.
 */
#[ORM\Entity(repositoryClass: ProjectEmbeddingRepository::class)]
class ProjectEmbedding
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\OneToOne(targetEntity: Project::class)]
    #[ORM\JoinColumn(name: 'project_id', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
    private Project $project;

    #[ORM\Column(type: 'vector')]
    private Vector $embedding;

    #[ORM\Column(length: 64)]
    private string $model;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $embeddedAt;

    public function __construct(
        Project $project,
        Vector $embedding,
        string $model,
        \DateTimeImmutable $embeddedAt,
    ) {
        $this->project = $project;
        $this->embedding = $embedding;
        $this->model = $model;
        $this->embeddedAt = $embeddedAt;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getProject(): Project
    {
        return $this->project;
    }

    public function getEmbedding(): Vector
    {
        return $this->embedding;
    }

    public function getModel(): string
    {
        return $this->model;
    }

    public function getEmbeddedAt(): \DateTimeImmutable
    {
        return $this->embeddedAt;
    }
}
