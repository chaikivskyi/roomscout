<?php

namespace App\Tests\Factory;

use App\Project\Entity\ProjectImageVersion;
use Zenstruck\Foundry\Persistence\PersistentObjectFactory;

/**
 * @extends PersistentObjectFactory<ProjectImageVersion>
 */
final class ProjectImageVersionFactory extends PersistentObjectFactory
{
    public static function class(): string
    {
        return ProjectImageVersion::class;
    }

    protected function defaults(): array
    {
        return [
            'project' => ProjectFactory::new(),
            'imagePath' => self::faker()->uuid().'/image.jpg',
        ];
    }
}
