<?php

namespace App\Catalog\Entity;

use App\Catalog\Api\ProductInterface;
use App\Catalog\Repository\ProductRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Types\UuidType;
use Symfony\Component\Uid\Uuid;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: ProductRepository::class)]
#[ORM\UniqueConstraint(name: 'uniq_product_external_id', columns: ['external_id'])]
#[ORM\UniqueConstraint(name: 'uniq_product_uuid', columns: ['uuid'])]
class Product implements ProductInterface
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    /**
     * Stable, application-assigned identifier, safe to expose publicly (e.g. as
     * the thumbnail storage path) without leaking the source's product id.
     */
    #[ORM\Column(type: UuidType::NAME)]
    private Uuid $uuid;

    #[ORM\Column(length: 255, nullable: true)]
    #[Assert\Length(max: 255)]
    private ?string $externalId = null;

    #[ORM\Column(length: 255)]
    #[Assert\NotBlank]
    #[Assert\Length(max: 255)]
    private ?string $title = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $description = null;

    #[ORM\Column(length: 2048)]
    #[Assert\NotBlank]
    #[Assert\Url]
    #[Assert\Length(max: 2048)]
    private ?string $url = null;

    #[ORM\Column(length: 2048)]
    #[Assert\NotBlank]
    #[Assert\Length(max: 2048)]
    private ?string $thumbnailUrl = null;

    #[ORM\Column(length: 64, nullable: true)]
    private ?string $thumbnailHash = null;

    #[ORM\Column(nullable: true, type: Types::FLOAT)]
    #[Assert\PositiveOrZero]
    private ?float $price = null;

    #[ORM\ManyToOne(targetEntity: Category::class, inversedBy: 'products')]
    #[ORM\JoinColumn(name: 'category_id', referencedColumnName: 'id', nullable: false)]
    #[Assert\NotNull]
    private ?Category $category = null;

    // Dimensions
    #[ORM\Column(nullable: true, type: Types::FLOAT)]
    #[Assert\Positive]
    private ?float $widthSm = null;

    #[ORM\Column(nullable: true, type: Types::FLOAT)]
    #[Assert\Positive]
    private ?float $heightSm = null;

    #[ORM\Column(nullable: true, type: Types::FLOAT)]
    #[Assert\Positive]
    private ?float $depthSm = null;

    public function __construct()
    {
        $this->uuid = Uuid::v7();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getUuid(): Uuid
    {
        return $this->uuid;
    }

    public function getExternalId(): ?string
    {
        return $this->externalId;
    }

    public function setExternalId(?string $externalId): static
    {
        $this->externalId = $externalId;

        return $this;
    }

    public function getTitle(): ?string
    {
        return $this->title;
    }

    public function setTitle(string $title): static
    {
        $this->title = $title;

        return $this;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(?string $description): static
    {
        $this->description = $description;

        return $this;
    }

    public function getUrl(): ?string
    {
        return $this->url;
    }

    public function setUrl(string $url): static
    {
        $this->url = $url;

        return $this;
    }

    public function getThumbnailUrl(): ?string
    {
        return $this->thumbnailUrl;
    }

    public function setThumbnailUrl(string $thumbnailUrl): static
    {
        $this->thumbnailUrl = $thumbnailUrl;

        return $this;
    }

    public function getThumbnailHash(): ?string
    {
        return $this->thumbnailHash;
    }

    public function setThumbnailHash(?string $thumbnailHash): static
    {
        $this->thumbnailHash = $thumbnailHash;

        return $this;
    }

    public function getPrice(): ?float
    {
        return $this->price;
    }

    public function setPrice(?float $price): static
    {
        $this->price = $price;

        return $this;
    }

    public function getCategory(): ?Category
    {
        return $this->category;
    }

    public function setCategory(?Category $category): static
    {
        $this->category = $category;

        return $this;
    }

    public function getWidthSm(): ?float
    {
        return $this->widthSm;
    }

    public function setWidthSm(?float $widthSm): static
    {
        $this->widthSm = $widthSm;

        return $this;
    }

    public function getHeightSm(): ?float
    {
        return $this->heightSm;
    }

    public function setHeightSm(?float $heightSm): static
    {
        $this->heightSm = $heightSm;

        return $this;
    }

    public function getDepthSm(): ?float
    {
        return $this->depthSm;
    }

    public function setDepthSm(?float $depthSm): static
    {
        $this->depthSm = $depthSm;

        return $this;
    }
}
