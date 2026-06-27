<?php

namespace App\Catalog\Entity;

use App\Catalog\Repository\ProductRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: ProductRepository::class)]
class Product
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255, nullable: true)]
    #[Assert\Length(max: 255)]
    private ?string $external_id = null;

    #[ORM\Column(length: 255)]
    #[Assert\NotBlank]
    #[Assert\Length(max: 255)]
    private ?string $title = null;

    #[ORM\Column(nullable: true)]
    #[Assert\Length(max: 255)]
    private ?string $description = null;

    #[ORM\Column(length: 2048)]
    #[Assert\NotBlank]
    #[Assert\Url]
    #[Assert\Length(max: 2048)]
    private ?string $url = null;

    #[ORM\Column(length: 2048)]
    #[Assert\NotBlank]
    #[Assert\Url]
    #[Assert\Length(max: 2048)]
    private ?string $thumbnail_url = null;

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
    private ?float $width_sm = null;

    #[ORM\Column(nullable: true, type: Types::FLOAT)]
    #[Assert\Positive]
    private ?float $height_sm = null;

    #[ORM\Column(nullable: true, type: Types::FLOAT)]
    #[Assert\Positive]
    private ?float $depth_sm = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getExternalId(): ?string
    {
        return $this->external_id;
    }

    public function setExternalId(?string $external_id): static
    {
        $this->external_id = $external_id;

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
        return $this->thumbnail_url;
    }

    public function setThumbnailUrl(string $thumbnail_url): static
    {
        $this->thumbnail_url = $thumbnail_url;

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
        return $this->width_sm;
    }

    public function setWidthSm(?float $width_sm): static
    {
        $this->width_sm = $width_sm;

        return $this;
    }

    public function getHeightSm(): ?float
    {
        return $this->height_sm;
    }

    public function setHeightSm(?float $height_sm): static
    {
        $this->height_sm = $height_sm;

        return $this;
    }

    public function getDepthSm(): ?float
    {
        return $this->depth_sm;
    }

    public function setDepthSm(?float $depth_sm): static
    {
        $this->depth_sm = $depth_sm;

        return $this;
    }
}
