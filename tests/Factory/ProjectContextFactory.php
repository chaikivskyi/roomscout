<?php

namespace App\Tests\Factory;

use App\Project\Entity\ProjectContext;
use Zenstruck\Foundry\Persistence\PersistentObjectFactory;

/**
 * @extends PersistentObjectFactory<ProjectContext>
 */
final class ProjectContextFactory extends PersistentObjectFactory
{
    public static function class(): string
    {
        return ProjectContext::class;
    }

    protected function defaults(): array
    {
        return [
            'project' => ProjectFactory::new(),
            'prompt' => self::faker()->sentence(),
        ];
    }

    public function completed(): static
    {
        return $this->afterInstantiate(static function (ProjectContext $context): void {
            $context->markCompleted();
        });
    }
}
