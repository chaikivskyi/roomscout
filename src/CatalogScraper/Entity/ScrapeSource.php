<?php

namespace App\CatalogScraper\Entity;

use App\Catalog\Entity\Category;
use App\CatalogScraper\Enum\ProductField;
use App\CatalogScraper\Repository\ScrapeSourceRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: ScrapeSourceRepository::class)]
class ScrapeSource
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    #[Assert\NotBlank]
    #[Assert\Length(max: 255)]
    private ?string $title = null;

    #[ORM\Column(options: ['default' => true])]
    private bool $active = true;

    #[ORM\Column(length: 2048)]
    #[Assert\NotBlank]
    #[Assert\Url]
    #[Assert\Length(max: 2048)]
    private ?string $sourceUrl = null;

    #[ORM\ManyToOne(targetEntity: Category::class)]
    #[ORM\JoinColumn(name: 'category_id', referencedColumnName: 'id', nullable: false)]
    #[Assert\NotNull]
    private ?Category $category = null;

    #[ORM\Column(length: 1024)]
    #[Assert\NotBlank]
    #[Assert\Length(max: 1024)]
    private ?string $productUrlSelector = null;

    #[ORM\Column(length: 1024, nullable: true)]
    #[Assert\Length(max: 1024)]
    private ?string $nextPageSelector = null;

    /**
     * @var list<array{field: string, selector: string, attribute: ?string}>
     */
    #[ORM\Column(type: Types::JSONB)]
    #[Assert\Count(min: 1, minMessage: 'Add at least one field mapping.')]
    #[Assert\All([
        new Assert\Collection(
            fields: [
                'field' => [
                    new Assert\NotBlank(),
                    new Assert\Choice(callback: [ProductField::class, 'values']),
                ],
                'selector' => [
                    new Assert\NotBlank(),
                    new Assert\Length(max: 1024),
                ],
                'attribute' => new Assert\Optional([
                    new Assert\Length(max: 255),
                ]),
            ],
            allowExtraFields: false,
            allowMissingFields: false,
        ),
    ])]
    private array $mappings = [];

    public function getId(): ?int
    {
        return $this->id;
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

    public function isActive(): bool
    {
        return $this->active;
    }

    public function setActive(bool $active): static
    {
        $this->active = $active;

        return $this;
    }

    public function getSourceUrl(): ?string
    {
        return $this->sourceUrl;
    }

    public function setSourceUrl(string $sourceUrl): static
    {
        $this->sourceUrl = $sourceUrl;

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

    public function getProductUrlSelector(): ?string
    {
        return $this->productUrlSelector;
    }

    public function setProductUrlSelector(string $productUrlSelector): static
    {
        $this->productUrlSelector = $productUrlSelector;

        return $this;
    }

    public function getNextPageSelector(): ?string
    {
        return $this->nextPageSelector;
    }

    public function setNextPageSelector(?string $nextPageSelector): static
    {
        $this->nextPageSelector = $nextPageSelector;

        return $this;
    }

    /**
     * @return list<array{field: string, selector: string, attribute: ?string}>
     */
    public function getMappings(): array
    {
        return $this->mappings;
    }

    /**
     * @param list<array{field: string, selector: string, attribute: ?string}> $mappings
     */
    public function setMappings(array $mappings): static
    {
        $this->mappings = $mappings;

        return $this;
    }
}
