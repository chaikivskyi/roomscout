<?php

namespace App\Admin\Controller;

use App\Admin\Form\ScrapeRuleType;
use App\CatalogScraper\Entity\ScrapeSource;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\CollectionField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use EasyCorp\Bundle\EasyAdminBundle\Field\UrlField;

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
            UrlField::new('source_url', 'Source URL')->setRequired(true),
            CollectionField::new('rules')
                ->setEntryType(ScrapeRuleType::class)
                ->setEntryIsComplex(true)
                ->allowAdd()
                ->allowDelete()
                ->setHelp('Ordered list of scraping steps run top to bottom.')
                ->hideOnIndex(),
        ];
    }
}
