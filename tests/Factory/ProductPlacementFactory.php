<?php

namespace App\Tests\Factory;

use App\Placement\Entity\ProductPlacement;
use App\Project\Entity\ProjectContext;
use Zenstruck\Foundry\Persistence\PersistentObjectFactory;

/**
 * @extends PersistentObjectFactory<ProductPlacement>
 */
final class ProductPlacementFactory extends PersistentObjectFactory
{
    public static function class(): string
    {
        return ProductPlacement::class;
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
        // A placement's project must be the context's project; derive it
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
        return $this->afterInstantiate(static function (ProductPlacement $placement) use ($imagePath): void {
            $version = ProjectImageVersionFactory::createOne(array_filter([
                'project' => $placement->getProject(),
                'imagePath' => $imagePath,
            ]));
            $placement->markCompleted($version);
        });
    }

    public function failed(): static
    {
        return $this->afterInstantiate(static function (ProductPlacement $placement): void {
            $placement->markFailed();
        });
    }
}
