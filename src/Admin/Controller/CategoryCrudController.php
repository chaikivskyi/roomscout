<?php

namespace App\Admin\Controller;

use App\Catalog\Entity\Category;
use Doctrine\ORM\QueryBuilder;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\AssociationField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IntegerField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;

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
            IdField::new('id')->onlyOnIndex(),
            TextField::new('title'),
            AssociationField::new('parent')
                ->setRequired(false)
                ->setFormTypeOption('choice_label', 'pathTitle')
                ->setQueryBuilder(function (QueryBuilder $qb) use ($currentId): QueryBuilder {
                    if (null !== $currentId) {
                        $qb->andWhere('entity.id != :currentId')->setParameter('currentId', $currentId);
                    }

                    return $qb;
                })
                ->onlyOnForms(),
            IntegerField::new('level')->onlyOnIndex(),
            TextField::new('pathTitle', 'Path')->onlyOnIndex(),
        ];
    }
}
