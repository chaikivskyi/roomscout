<?php

namespace App\Placement\Command;

use App\Catalog\Entity\Product;
use App\CatalogSearch\Repository\ProjectProductMatchRepository;
use App\Placement\Entity\ProductPlacement;
use App\Placement\Exception\InvalidPlacementTarget;
use App\Placement\Exception\PlacementAlreadyRunning;
use App\Placement\Repository\ProductPlacementRepository;
use App\Project\Repository\ProjectContextRepository;
use App\Project\Service\OwnedProjectResolver;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler(bus: 'command.bus')]
final class CreatePlacementHandler
{
    public function __construct(
        private readonly OwnedProjectResolver $projectResolver,
        private readonly ProjectContextRepository $contexts,
        private readonly ProjectProductMatchRepository $matches,
        private readonly ProductPlacementRepository $placements,
        #[Autowire('%env(PLACEMENT_IMAGE_MODEL)%')]
        private readonly string $imageModel,
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    public function __invoke(CreatePlacement $command): void
    {
        $project = $this->projectResolver->resolve($command->projectId, $command->actorId);

        $context = $this->contexts->findOneForProject($project->getId(), $command->contextId)
            ?? throw InvalidPlacementTarget::unknownContext();

        if (!$this->matches->existsForContextAndProduct($context->getId(), $command->productId)) {
            throw InvalidPlacementTarget::productNotMatched();
        }

        $product = $this->entityManager->find(Product::class, $command->productId)
            ?? throw InvalidPlacementTarget::productNotMatched();

        if ($this->placements->hasActiveForProject($project->getId())) {
            throw new PlacementAlreadyRunning();
        }

        $placement = new ProductPlacement($project, $context, $product, $this->imageModel, $command->placementId);

        try {
            $this->placements->save($placement);
        } catch (UniqueConstraintViolationException $e) {
            throw new PlacementAlreadyRunning($e);
        }
    }
}
