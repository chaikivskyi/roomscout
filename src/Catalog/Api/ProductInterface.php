<?php

namespace App\Catalog\Api;

use App\Catalog\Entity\Category;
use Symfony\Component\Uid\Uuid;

interface ProductInterface
{
    public function getId(): ?int;

    public function getUuid(): Uuid;

    public function getExternalId(): ?string;

    public function setExternalId(?string $externalId): static;

    public function getTitle(): ?string;

    public function setTitle(string $title): static;

    public function getDescription(): ?string;

    public function setDescription(?string $description): static;

    public function getUrl(): ?string;

    public function setUrl(string $url): static;

    public function getThumbnailUrl(): ?string;

    public function setThumbnailUrl(string $thumbnailUrl): static;

    public function getPrice(): ?float;

    public function setPrice(?float $price): static;

    public function getCategory(): ?Category;

    public function setCategory(?Category $category): static;

    public function getWidthSm(): ?float;

    public function setWidthSm(?float $widthSm): static;

    public function getHeightSm(): ?float;

    public function setHeightSm(?float $heightSm): static;

    public function getDepthSm(): ?float;

    public function setDepthSm(?float $depthSm): static;
}
