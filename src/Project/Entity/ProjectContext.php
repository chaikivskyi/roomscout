<?php

namespace App\Project\Entity;

use App\Project\Enum\ProjectContextStatus;
use App\Project\Repository\ProjectContextRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Types\UuidType;
use Symfony\Component\Uid\Uuid;

#[ORM\Entity(repositoryClass: ProjectContextRepository::class)]
#[ORM\HasLifecycleCallbacks]
class ProjectContext
{
    #[ORM\Id]
    #[ORM\Column(type: UuidType::NAME)]
    private Uuid $id;

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column]
    private \DateTimeImmutable $updatedAt;

    #[ORM\Column(length: 16, enumType: ProjectContextStatus::class)]
    private ProjectContextStatus $status;

    public function __construct(
        #[ORM\ManyToOne(targetEntity: Project::class)]
        #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
        private readonly Project $project,
        #[ORM\Column(type: Types::TEXT)]
        private readonly string $prompt,
    ) {
        $this->id = Uuid::v7();
        $this->createdAt = new \DateTimeImmutable();
        $this->updatedAt = $this->createdAt;
        $this->status = ProjectContextStatus::Processing;
    }

    public function getStatus(): ProjectContextStatus
    {
        return $this->status;
    }

    public function markCompleted(): bool
    {
        if (ProjectContextStatus::Completed === $this->status) {
            return false;
        }

        $this->status = ProjectContextStatus::Completed;

        return true;
    }

    /**
     * @return bool whether the status changed
     */
    public function markFailed(): bool
    {
        if (ProjectContextStatus::Processing !== $this->status) {
            return false;
        }

        $this->status = ProjectContextStatus::Failed;

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

    public function getPrompt(): string
    {
        return $this->prompt;
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
