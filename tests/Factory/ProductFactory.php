<?php

namespace App\Tests\Factory;

use App\Catalog\Entity\Product;
use Zenstruck\Foundry\Persistence\PersistentObjectFactory;

/**
 * @extends PersistentObjectFactory<Product>
 */
final class ProductFactory extends PersistentObjectFactory
{
    public static function class(): string
    {
        return Product::class;
    }

    protected function defaults(): array
    {
        return [
            'title' => self::faker()->words(3, true),
            'url' => self::faker()->url(),
            'thumbnailUrl' => self::faker()->uuid().'/thumbnail.jpg',
            'category' => CategoryFactory::new(),
        ];
    }
}
