<?php

namespace App\Admin\Controller;

use App\Admin\Form\ScrapeMappingType;
use App\CatalogScraper\Entity\ScrapeSource;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\AssociationField;
use EasyCorp\Bundle\EasyAdminBundle\Field\BooleanField;
use EasyCorp\Bundle\EasyAdminBundle\Field\CollectionField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use EasyCorp\Bundle\EasyAdminBundle\Field\UrlField;

/**
 * @extends AbstractCrudController<ScrapeSource>
 */
class ScrapeSourceCrudController extends AbstractCrudController
{
    public static function getEntityFqcn(): string
    {
        return ScrapeSource::class;
    }

    public function configureFields(string $pageName): iterable
    {
        return [
            IdField::new('id')->onlyOnIndex(),
            TextField::new('title')->setRequired(true),
            BooleanField::new('active'),
            AssociationField::new('category')->setRequired(true),
            UrlField::new('sourceUrl', 'Category URL')->setRequired(true),
            TextField::new('productUrlSelector', 'Product URL selector')
                ->setRequired(true)
                ->setHelp('CSS selector matching links to each product page on the listing.')
                ->hideOnIndex(),
            TextField::new('nextPageSelector', 'Next page selector')
                ->setHelp('CSS selector for the pagination "next" link. Leave empty for a single page.')
                ->hideOnIndex(),
            CollectionField::new('mappings')
                ->setEntryType(ScrapeMappingType::class)
                ->setEntryIsComplex(true)
                ->allowAdd()
                ->allowDelete()
                ->setHelp('Maps each Product field to the CSS selector that finds it on a product page.')
                ->hideOnIndex(),
        ];
    }
}
