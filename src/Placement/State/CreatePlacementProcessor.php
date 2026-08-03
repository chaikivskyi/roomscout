<?php

namespace App\Placement\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Catalog\Entity\Product;
use App\CatalogSearch\Repository\ProjectProductMatchRepository;
use App\Placement\ApiResource\PlacementOutput;
use App\Placement\ApiResource\PlacementRequest;
use App\Placement\Entity\ProductPlacement;
use App\Placement\Repository\ProductPlacementRepository;
use App\Placement\Service\PlacementOutputMapper;
use App\Project\Repository\ProjectContextRepository;
use App\Project\Service\OwnedProjectResolver;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;
use Symfony\Component\Uid\Uuid;

/**
 * @implements ProcessorInterface<PlacementRequest, PlacementOutput>
 */
final class CreatePlacementProcessor implements ProcessorInterface
{
    private const CONFLICT_MESSAGE = 'A placement is already being generated for this project.';

    public function __construct(
        private readonly OwnedProjectResolver $projectResolver,
        private readonly ProjectContextRepository $contexts,
        private readonly ProjectProductMatchRepository $matches,
        private readonly ProductPlacementRepository $placements,
        #[Autowire('%env(PLACEMENT_IMAGE_MODEL)%')]
        private readonly string $imageModel,
        private readonly PlacementOutputMapper $outputMapper,
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): PlacementOutput
    {
        $project = $this->projectResolver->resolve($uriVariables['projectId'] ?? null);

        $projectContext = $this->contexts->findOneForProject($project->getId(), Uuid::fromString($data->contextId));

        if (null === $projectContext) {
            throw new UnprocessableEntityHttpException('Unknown context for this project.');
        }

        $productId = Uuid::fromString($data->productId);

        if (!$this->matches->existsForContextAndProduct($projectContext->getId(), $productId)) {
            throw new UnprocessableEntityHttpException('Product is not matched to this context.');
        }

        $product = $this->entityManager->find(Product::class, $productId)
            ?? throw new UnprocessableEntityHttpException('Product is not matched to this context.');

        if ($this->placements->hasActiveForProject($project->getId())) {
            throw new ConflictHttpException(self::CONFLICT_MESSAGE);
        }

        $placement = new ProductPlacement($project, $projectContext, $product, $this->imageModel);

        try {
            $this->placements->save($placement);
        } catch (UniqueConstraintViolationException $e) {
            throw new ConflictHttpException(self::CONFLICT_MESSAGE, $e);
        }

        return $this->outputMapper->map($placement);
    }
}
