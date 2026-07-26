<?php

namespace App\Tests\Factory;

use App\CatalogSearch\Entity\ProjectProductMatch;
use Zenstruck\Foundry\Persistence\PersistentObjectFactory;

/**
 * @extends PersistentObjectFactory<ProjectProductMatch>
 */
final class ProjectProductMatchFactory extends PersistentObjectFactory
{
    public static function class(): string
    {
        return ProjectProductMatch::class;
    }

    protected function defaults(): array
    {
        return [
            'context' => ProjectContextFactory::new(),
            'product' => ProductFactory::new(),
            'matchScore' => self::faker()->randomFloat(4, 0.2, 0.95),
            'model' => 'embed-test-1.0',
            'matchedAt' => \DateTimeImmutable::createFromMutable(self::faker()->dateTime()),
        ];
    }
}
