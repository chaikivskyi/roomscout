<?php

namespace App\Admin\Controller;

use App\Catalog\Entity\Product;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\AssociationField;
use EasyCorp\Bundle\EasyAdminBundle\Field\ImageField;
use EasyCorp\Bundle\EasyAdminBundle\Field\NumberField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextareaField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use EasyCorp\Bundle\EasyAdminBundle\Field\UrlField;

/**
 * @extends AbstractCrudController<Product>
 */
class ProductCrudController extends AbstractCrudController
{
    public static function getEntityFqcn(): string
    {
        return Product::class;
    }

    public function configureFields(string $pageName): iterable
    {
        return [
            TextField::new('title')->setRequired(true),
            AssociationField::new('category')->setRequired(true),
            TextField::new('externalId', 'External ID')->setRequired(false)->hideOnIndex(),
            TextareaField::new('description')->setRequired(false)->hideOnIndex(),
            UrlField::new('url')->setRequired(true)->hideOnIndex(),
            ImageField::new('thumbnailUrl', 'Thumbnail')
                ->setRequired(false)
                ->setFlysystemStorage('product_thumbnails.storage')
                ->setUploadDir('/')
                ->setUploadedFileNamePattern('[uuid].[extension]')
                ->mimeTypes('image/jpeg,image/png,image/webp,image/gif')
                ->maxSize('10Mi')
                ->isDeletable(false)
                ->setSortable(false)
                ->setFormTypeOption('data_class', null),
            NumberField::new('price')->setNumDecimals(2)->setRequired(false),
            NumberField::new('widthSm', 'Width (cm)')->setRequired(false)->hideOnIndex(),
            NumberField::new('heightSm', 'Height (cm)')->setRequired(false)->hideOnIndex(),
            NumberField::new('depthSm', 'Depth (cm)')->setRequired(false)->hideOnIndex(),
        ];
    }
}
