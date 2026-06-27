<?php

namespace App\Admin\Controller;

use App\Catalog\Entity\Product;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\AssociationField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;
use EasyCorp\Bundle\EasyAdminBundle\Field\NumberField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextareaField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use EasyCorp\Bundle\EasyAdminBundle\Field\UrlField;

class ProductCrudController extends AbstractCrudController
{
    public static function getEntityFqcn(): string
    {
        return Product::class;
    }

    public function configureFields(string $pageName): iterable
    {
        return [
            IdField::new('id')->onlyOnIndex(),
            TextField::new('title')->setRequired(true),
            AssociationField::new('category')->setRequired(true),
            TextField::new('external_id', 'External ID')->setRequired(false)->hideOnIndex(),
            TextareaField::new('description')->setRequired(false)->hideOnIndex(),
            UrlField::new('url')->setRequired(true)->hideOnIndex(),
            UrlField::new('thumbnail_url', 'Thumbnail URL')->setRequired(true)->hideOnIndex(),
            NumberField::new('price')->setNumDecimals(2)->setRequired(false),
            NumberField::new('width_sm', 'Width (cm)')->setRequired(false)->hideOnIndex(),
            NumberField::new('height_sm', 'Height (cm)')->setRequired(false)->hideOnIndex(),
            NumberField::new('depth_sm', 'Depth (cm)')->setRequired(false)->hideOnIndex(),
        ];
    }
}
