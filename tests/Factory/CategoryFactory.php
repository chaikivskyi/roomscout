<?php

namespace App\Tests\Factory;

use App\Catalog\Entity\Category;
use Zenstruck\Foundry\Persistence\PersistentObjectFactory;

/**
 * @extends PersistentObjectFactory<Category>
 */
final class CategoryFactory extends PersistentObjectFactory
{
    public static function class(): string
    {
        return Category::class;
    }

    protected function defaults(): array
    {
        return [
            'title' => self::faker()->words(2, true),
        ];
    }
}
