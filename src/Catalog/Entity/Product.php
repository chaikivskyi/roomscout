<?php

namespace App\Catalog\Entity;

use App\Catalog\Repository\ProductRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: ProductRepository::class)]
class Product
{
    // Identity
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $external_id = null;

    // Content
    #[ORM\Column(length: 255)]
    private ?string $title = null;

    #[ORM\Column(nullable: true)]
    private ?string $description = null;

    #[ORM\Column(length: 2048)]
    private ?string $url = null;

    #[ORM\Column(length: 2048)]
    private ?string $thumbnail_url = null;

    // Pricing
    #[ORM\Column(nullable: true)]
    private ?float $price = null;

    // Categorization
    #[ORM\ManyToOne(targetEntity: Category::class, inversedBy: 'products')]
    #[ORM\JoinColumn(name: 'category_id', referencedColumnName: 'id', nullable: false)]
    private ?Category $category = null;

    // Dimensions
    #[ORM\Column(nullable: true)]
    private ?float $width_sm = null;

    #[ORM\Column(nullable: true)]
    private ?float $height_sm = null;

    #[ORM\Column(nullable: true)]
    private ?float $depth_sm = null;

    // Identity

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

    // Content

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

    // Pricing

    public function getPrice(): ?float
    {
        return $this->price;
    }

    public function setPrice(?float $price): static
    {
        $this->price = $price;

        return $this;
    }

    // Categorization

    public function getCategory(): ?Category
    {
        return $this->category;
    }

    public function setCategory(?Category $category): static
    {
        $this->category = $category;

        return $this;
    }

    // Dimensions

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
