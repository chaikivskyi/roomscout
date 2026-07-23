<?php

namespace App\Project\Entity;

use App\Project\Enum\ProjectStatus;
use App\Project\Repository\ProjectRepository;
use App\Identity\Entity\User;
use DateTimeImmutable;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Types\UuidType;
use Symfony\Component\Uid\Uuid;

#[ORM\Entity(repositoryClass: ProjectRepository::class)]
#[ORM\HasLifecycleCallbacks]
class Project
{
    #[ORM\Id]
    #[ORM\Column(type: UuidType::NAME)]
    private Uuid $id;

    #[ORM\Column]
    private DateTimeImmutable $createdAt;

    #[ORM\Column]
    private DateTimeImmutable $updatedAt;

    #[ORM\Column(length: 16, enumType: ProjectStatus::class)]
    private ProjectStatus $status;

    public function __construct(
        #[ORM\ManyToOne(targetEntity: User::class)]
        #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
        private readonly User $user,
        #[ORM\Column(length: 255)]
        private readonly string $imagePath,
        #[ORM\Column(type: Types::TEXT)]
        private string $prompt,
    ) {
        $this->id = Uuid::v7();
        $this->createdAt = new DateTimeImmutable();
        $this->updatedAt = $this->createdAt;
        $this->status = ProjectStatus::Processing;
    }

    public function getStatus(): ProjectStatus
    {
        return $this->status;
    }

    /**
     * @return bool whether the status changed
     */
    public function markCompleted(): bool
    {
        if (ProjectStatus::Completed === $this->status) {
            return false;
        }

        $this->status = ProjectStatus::Completed;

        return true;
    }

    /**
     * @return bool whether the status changed
     */
    public function markFailed(): bool
    {
        if (ProjectStatus::Processing !== $this->status) {
            return false;
        }

        $this->status = ProjectStatus::Failed;

        return true;
    }

    public function getId(): Uuid
    {
        return $this->id;
    }

    public function getUser(): User
    {
        return $this->user;
    }

    public function getImagePath(): string
    {
        return $this->imagePath;
    }

    public function getPrompt(): string
    {
        return $this->prompt;
    }

    public function setPrompt(string $prompt): static
    {
        $this->prompt = $prompt;

        return $this;
    }

    public function getCreatedAt(): DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getUpdatedAt(): DateTimeImmutable
    {
        return $this->updatedAt;
    }

    #[ORM\PreUpdate]
    public function touchUpdatedAt(): void
    {
        $this->updatedAt = new DateTimeImmutable();
    }
}
