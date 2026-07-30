<?php

namespace App\CatalogSearch\Entity;

use App\CatalogSearch\Repository\ProjectContextEmbeddingRepository;
use App\Project\Entity\ProjectContext;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Pgvector\Vector;
use Symfony\Bridge\Doctrine\Types\UuidType;
use Symfony\Component\Uid\Uuid;

#[ORM\Entity(repositoryClass: ProjectContextEmbeddingRepository::class)]
class ProjectContextEmbedding
{
    #[ORM\Id]
    #[ORM\Column(type: UuidType::NAME)]
    private Uuid $id;

    #[ORM\OneToOne(targetEntity: ProjectContext::class)]
    #[ORM\JoinColumn(name: 'context_id', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
    private ProjectContext $context;

    #[ORM\Column(type: 'vector')]
    private Vector $embedding;

    #[ORM\Column(length: 64)]
    private string $model;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $embeddedAt;

    public function __construct(
        ProjectContext $context,
        Vector $embedding,
        string $model,
        \DateTimeImmutable $embeddedAt,
    ) {
        $this->id = Uuid::v7();
        $this->context = $context;
        $this->embedding = $embedding;
        $this->model = $model;
        $this->embeddedAt = $embeddedAt;
    }

    public function getId(): Uuid
    {
        return $this->id;
    }

    public function getContext(): ProjectContext
    {
        return $this->context;
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
