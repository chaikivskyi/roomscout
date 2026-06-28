<?php

namespace App\CatalogScraper\Entity;

use App\CatalogScraper\Enum\ScrapeAction;
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

    #[ORM\Column(length: 2048)]
    #[Assert\NotBlank]
    #[Assert\Url]
    #[Assert\Length(max: 2048)]
    private ?string $source_url = null;

    #[ORM\Column(length: 255)]
    #[Assert\NotBlank]
    #[Assert\Length(max: 255)]
    private ?string $title = null;

    /**
     * Ordered list of scraping steps, each shaped like
     * ['action' => 'click', 'selector' => '.btn', 'value' => null].
     *
     * @var list<array{action: string, selector: string, value: ?string}>
     */
    #[ORM\Column(type: Types::JSONB)]
    #[Assert\Count(min: 1, minMessage: 'Add at least one scraping rule.')]
    #[Assert\All([
        new Assert\Collection(
            fields: [
                'action' => [
                    new Assert\NotBlank(),
                    new Assert\Choice(callback: [ScrapeAction::class, 'values']),
                ],
                'selector' => [
                    new Assert\NotBlank(),
                    new Assert\Length(max: 1024),
                ],
                'value' => new Assert\Optional([
                    new Assert\Length(max: 1024),
                ]),
            ],
            allowExtraFields: false,
            allowMissingFields: false,
        ),
    ])]
    private array $rules = [];

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getSourceUrl(): ?string
    {
        return $this->source_url;
    }

    public function setSourceUrl(string $source_url): static
    {
        $this->source_url = $source_url;

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

    /**
     * @return list<array{action: string, selector: string, value: ?string}>
     */
    public function getRules(): array
    {
        return $this->rules;
    }

    /**
     * @param list<array{action: string, selector: string, value: ?string}> $rules
     */
    public function setRules(array $rules): static
    {
        $this->rules = $rules;

        return $this;
    }
}
