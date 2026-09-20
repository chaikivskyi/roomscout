<?php

namespace App\Tests\Factory;

use App\Visualization\Entity\ProductVisualization;
use App\Project\Entity\ProjectContext;
use Zenstruck\Foundry\Persistence\PersistentObjectFactory;

/**
 * @extends PersistentObjectFactory<ProductVisualization>
 */
final class ProductVisualizationFactory extends PersistentObjectFactory
{
    public static function class(): string
    {
        return ProductVisualization::class;
    }

    protected function defaults(): array
    {
        return [
            'context' => ProjectContextFactory::new(),
            'product' => ProductFactory::new(),
            'model' => 'gemini-test-image',
        ];
    }

    protected function initialize(): static
    {
        // A visualization's project must be the context's project; derive it
        // unless a test passes 'project' explicitly.
        return $this->beforeInstantiate(static function (array $parameters): array {
            if (!isset($parameters['project'])) {
                $context = $parameters['context'];

                if ($context instanceof ProjectContextFactory) {
                    $context = $context->create();
                }

                \assert($context instanceof ProjectContext);
                $parameters['context'] = $context;
                $parameters['project'] = $context->getProject();
            }

            return $parameters;
        });
    }

    public function completed(?string $imagePath = null): static
    {
        return $this->afterInstantiate(static function (ProductVisualization $visualization) use ($imagePath): void {
            $version = ProjectImageVersionFactory::createOne(array_filter([
                'project' => $visualization->getProject(),
                'imagePath' => $imagePath,
            ]));
            $visualization->markCompleted($version);
        });
    }

    public function failed(): static
    {
        return $this->afterInstantiate(static function (ProductVisualization $visualization): void {
            $visualization->markFailed();
        });
    }
}
