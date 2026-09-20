<?php

namespace App\Visualization\Command;

use App\Catalog\Entity\Product;
use App\CatalogSearch\Repository\ProjectProductMatchRepository;
use App\Visualization\Entity\ProductVisualization;
use App\Visualization\Exception\InvalidVisualizationTarget;
use App\Visualization\Exception\VisualizationAlreadyRunning;
use App\Visualization\Repository\ProductVisualizationRepository;
use App\Project\Repository\ProjectContextRepository;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler(bus: 'command.bus')]
final class CreateVisualizationHandler
{
    public function __construct(
        private readonly ProjectContextRepository $contexts,
        private readonly ProjectProductMatchRepository $matches,
        private readonly ProductVisualizationRepository $visualizations,
        #[Autowire('%env(VISUALIZATION_IMAGE_MODEL)%')]
        private readonly string $imageModel,
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    public function __invoke(CreateVisualization $command): void
    {
        $context = $this->contexts->find($command->contextId)
            ?? throw InvalidVisualizationTarget::unknownContext();

        $project = $context->getProject();

        if (!$this->matches->existsForContextAndProduct($context->getId(), $command->productId)) {
            throw InvalidVisualizationTarget::productNotMatched();
        }

        $product = $this->entityManager->find(Product::class, $command->productId)
            ?? throw InvalidVisualizationTarget::productNotMatched();

        if ($this->visualizations->hasActiveForProject($project->getId())) {
            throw new VisualizationAlreadyRunning();
        }

        $visualization = new ProductVisualization($project, $context, $product, $this->imageModel, $command->visualizationId);

        try {
            $this->visualizations->save($visualization);
        } catch (UniqueConstraintViolationException $e) {
            throw new VisualizationAlreadyRunning($e);
        }
    }
}
