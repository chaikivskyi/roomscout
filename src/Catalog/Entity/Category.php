<?php

namespace App\Catalog\Entity;

use App\Catalog\Repository\CategoryRepository;
use App\Catalog\Validator\ValidCategoryTree;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: CategoryRepository::class)]
#[ValidCategoryTree]
class Category
{
    public const MAX_DEPTH = 4;

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    #[Assert\Length(max: 255)]
    #[Assert\NotBlank]
    private ?string $title = null;

    /**
     * @var Collection<int, Product>
     */
    #[ORM\OneToMany(targetEntity: Product::class, mappedBy: 'category')]
    private Collection $products;

    #[ORM\ManyToOne(targetEntity: self::class, inversedBy: 'children')]
    #[ORM\JoinColumn(name: 'parent_category_id', nullable: true)]
    private ?self $parent = null;

    /**
     * @var Collection<int, self>
     */
    #[ORM\OneToMany(targetEntity: self::class, mappedBy: 'parent')]
    #[ORM\OrderBy(['title' => 'ASC'])]
    private Collection $children;

    #[ORM\Column(options: ['default' => 1])]
    private int $level = 1;

    #[ORM\Column(type: Types::TEXT, options: ['default' => ''])]
    private string $pathTitle = '';

    public function __construct()
    {
        $this->products = new ArrayCollection();
        $this->children = new ArrayCollection();
    }

    public function __toString(): string
    {
        return $this->title ?? '';
    }

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

    /**
     * @return Collection<int, Product>
     */
    public function getProducts(): Collection
    {
        return $this->products;
    }

    public function getParent(): ?self
    {
        return $this->parent;
    }

    public function setParent(?self $parent): static
    {
        $this->parent = $parent;

        return $this;
    }

    /**
     * @return Collection<int, self>
     */
    public function getChildren(): Collection
    {
        return $this->children;
    }

    public function getLevel(): int
    {
        return $this->level;
    }

    public function getPathTitle(): string
    {
        return $this->pathTitle;
    }

    public function refreshTreeFields(): bool
    {
        $level = 1;
        $titles = [$this->title ?? ''];
        for ($ancestor = $this->parent; null !== $ancestor && $ancestor !== $this; $ancestor = $ancestor->getParent()) {
            ++$level;
            array_unshift($titles, $ancestor->getTitle() ?? '');
        }

        $pathTitle = implode(' › ', $titles);

        if ($level === $this->level && $pathTitle === $this->pathTitle) {
            return false;
        }

        $this->level = $level;
        $this->pathTitle = $pathTitle;

        return true;
    }
}
