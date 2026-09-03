<?php

namespace App\Admin\Controller;

use App\Catalog\Entity\Category;
use Doctrine\ORM\QueryBuilder;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\AssociationField;
use EasyCorp\Bundle\EasyAdminBundle\Field\ImageField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IntegerField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use Symfony\Bridge\Doctrine\Types\UuidType;

/**
 * @extends AbstractCrudController<Category>
 */
class CategoryCrudController extends AbstractCrudController
{
    public static function getEntityFqcn(): string
    {
        return Category::class;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud->setDefaultSort(['pathTitle' => 'ASC']);
    }

    public function configureFields(string $pageName): iterable
    {
        $currentId = $this->getContext()?->getEntity()?->getPrimaryKeyValue();

        return [
            TextField::new('title'),
            ImageField::new('iconUrl', 'Icon')
                ->setRequired(false)
                ->setFlysystemStorage('category_icons.storage')
                ->setUploadDir('/')
                ->setUploadedFileNamePattern('[uuid].[extension]')
                ->mimeTypes('image/png,image/webp')
                ->maxSize('100k')
                ->isDeletable(false)
                ->setSortable(false)
                ->setFormTypeOption('data_class', null),
            AssociationField::new('parent')
                ->setRequired(false)
                ->setFormTypeOption('choice_label', 'pathTitle')
                ->setQueryBuilder(function (QueryBuilder $qb) use ($currentId): QueryBuilder {
                    if (null !== $currentId) {
                        $qb->andWhere('entity.id != :currentId')->setParameter('currentId', $currentId, UuidType::NAME);
                    }

                    return $qb;
                })
                ->onlyOnForms(),
            IntegerField::new('level')->onlyOnIndex(),
            TextField::new('pathTitle', 'Path')->onlyOnIndex(),
        ];
    }
}
