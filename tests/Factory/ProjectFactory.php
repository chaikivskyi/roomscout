<?php

namespace App\Tests\Factory;

use App\Project\Entity\Project;
use Zenstruck\Foundry\Persistence\PersistentObjectFactory;

/**
 * @extends PersistentObjectFactory<Project>
 */
final class ProjectFactory extends PersistentObjectFactory
{
    public static function class(): string
    {
        return Project::class;
    }

    protected function defaults(): array
    {
        return [
            'user' => UserFactory::new(),
        ];
    }
}
