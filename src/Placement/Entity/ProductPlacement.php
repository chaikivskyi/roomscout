<?php

namespace App\Placement\Entity;

use App\Catalog\Entity\Product;
use App\Placement\Enum\PlacementStatus;
use App\Placement\Repository\ProductPlacementRepository;
use App\Project\Entity\Project;
use App\Project\Entity\ProjectContext;
use App\Project\Entity\ProjectImageVersion;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Types\UuidType;
use Symfony\Component\Uid\Uuid;

#[ORM\Entity(repositoryClass: ProductPlacementRepository::class)]
#[ORM\HasLifecycleCallbacks]
#[ORM\UniqueConstraint(
    name: 'uniq_active_placement_per_project',
    columns: ['project_id'],
    options: ['where' => "((status)::text = 'processing'::text)"],
)]
class ProductPlacement
{
    #[ORM\Id]
    #[ORM\Column(type: UuidType::NAME)]
    private Uuid $id;

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column]
    private \DateTimeImmutable $updatedAt;

    #[ORM\Column(length: 16, enumType: PlacementStatus::class)]
    private PlacementStatus $status;

    #[ORM\ManyToOne(targetEntity: ProjectContext::class)]
    #[ORM\JoinColumn(name: 'context_id', referencedColumnName: 'id', onDelete: 'SET NULL')]
    private ?ProjectContext $context;

    #[ORM\ManyToOne(targetEntity: Product::class)]
    #[ORM\JoinColumn(name: 'product_id', referencedColumnName: 'id', onDelete: 'SET NULL')]
    private ?Product $product;

    #[ORM\Column(type: Types::TEXT)]
    private readonly string $prompt;

    #[ORM\OneToOne(targetEntity: ProjectImageVersion::class)]
    #[ORM\JoinColumn(name: 'result_version_id', referencedColumnName: 'id', onDelete: 'SET NULL')]
    private ?ProjectImageVersion $resultVersion;

    public function __construct(
        #[ORM\ManyToOne(targetEntity: Project::class)]
        #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
        private readonly Project $project,
        ProjectContext $context,
        Product $product,
        #[ORM\Column(length: 64)]
        private readonly string $model,
    ) {
        $this->id = Uuid::v7();
        $this->createdAt = new \DateTimeImmutable();
        $this->updatedAt = $this->createdAt;
        $this->status = PlacementStatus::Processing;
        $this->context = $context;
        $this->product = $product;
        $this->prompt = $context->getPrompt();
        $this->resultVersion = null;
    }

    public function getStatus(): PlacementStatus
    {
        return $this->status;
    }

    public function markCompleted(ProjectImageVersion $resultVersion): bool
    {
        if (PlacementStatus::Completed === $this->status) {
            return false;
        }

        $this->status = PlacementStatus::Completed;
        $this->resultVersion = $resultVersion;

        return true;
    }

    public function markFailed(): bool
    {
        if (PlacementStatus::Processing !== $this->status) {
            return false;
        }

        $this->status = PlacementStatus::Failed;

        return true;
    }

    public function getId(): Uuid
    {
        return $this->id;
    }

    public function getProject(): Project
    {
        return $this->project;
    }

    public function getContext(): ?ProjectContext
    {
        return $this->context;
    }

    public function getProduct(): ?Product
    {
        return $this->product;
    }

    public function getPrompt(): string
    {
        return $this->prompt;
    }

    public function getModel(): string
    {
        return $this->model;
    }

    public function getResultVersion(): ?ProjectImageVersion
    {
        return $this->resultVersion;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getUpdatedAt(): \DateTimeImmutable
    {
        return $this->updatedAt;
    }

    #[ORM\PreUpdate]
    public function touchUpdatedAt(): void
    {
        $this->updatedAt = new \DateTimeImmutable();
    }
}
