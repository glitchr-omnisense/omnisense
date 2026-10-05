<?php

namespace App\Controller\Admin\Crud;

use App\Entity\Follow;
use Base\Admin\Config\Action;
use Base\Admin\Config\Actions;
use Base\Admin\Config\Crud;
use Base\Admin\Controller\AbstractCrudController;
use Base\Field\AssociationField;
use Base\Field\DateTimeField;
use Base\Field\IdField;
use Base\Field\TextField;

/** Who follows which package: read, and removed when someone asks for it. */
class FollowCrudController extends AbstractCrudController
{
    public static function getEntityFqcn(): string
    {
        return Follow::class;
    }

    public static function getPreferredIcon(): ?string
    {
        return 'fa-solid fa-bell';
    }

    public function configureCrud(Crud $crud): Crud
    {
        return parent::configureCrud($crud)->setDefaultSort(['createdAt' => 'DESC']);
    }

    public function configureActions(Actions $actions): Actions
    {
        return parent::configureActions($actions)->disable(Action::NEW, Action::EDIT);
    }

    public function configureFields(string $pageName): iterable
    {
        yield IdField::new('id')->onlyOnIndex();
        yield AssociationField::new('user', 'Membre')->setColumns(4);
        yield AssociationField::new('package', 'Paquet')->setColumns(4);
        yield DateTimeField::new('createdAt', 'Depuis le')->setColumns(2);
        yield TextField::new('sinceVersion', 'Version ce jour-là')->setColumns(2);
    }
}
