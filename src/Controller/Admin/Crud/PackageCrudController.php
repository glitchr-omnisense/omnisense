<?php

namespace App\Controller\Admin\Crud;

use App\Catalogue\Synchronizer;
use App\Entity\Package;
use Base\Admin\Attribute\AdminAction;
use Base\Admin\Config\Action;
use Base\Admin\Config\Actions;
use Base\Admin\Config\Crud;
use Base\Admin\Controller\AbstractCrudController;
use Base\Field\BooleanField;
use Base\Field\DateTimeField;
use Base\Field\IdField;
use Base\Field\IntegerField;
use Base\Field\TextareaField;
use Base\Field\TextField;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Contracts\Service\Attribute\Required;

/**
 * The catalogue's packages, as the last run of `app:packages:sync` wrote
 * them. Read only: every field comes from the package's repository or its
 * registry, and is corrected there. One action: read the repositories
 * again, now.
 */
class PackageCrudController extends AbstractCrudController
{
    private Synchronizer $synchronizer;

    #[Required]
    public function setSynchronizer(Synchronizer $synchronizer): void
    {
        $this->synchronizer = $synchronizer;
    }

    public static function getEntityFqcn(): string
    {
        return Package::class;
    }

    public static function getPreferredIcon(): ?string
    {
        return 'fa-solid fa-cube';
    }

    public function configureCrud(Crud $crud): Crud
    {
        return parent::configureCrud($crud)->setDefaultSort(['slug' => 'ASC']);
    }

    public function configureActions(Actions $actions): Actions
    {
        return parent::configureActions($actions)
            ->disable(Action::NEW, Action::EDIT, Action::DELETE, Action::BATCH_DELETE)
            ->add(Actions::PAGE_INDEX, Action::new('synchronize', 'Relire les dépôts', 'fa-solid fa-rotate')
                ->createAsGlobalAction()
                ->linkToCrudAction('synchronize')
                ->askConfirmation('Relire les dépôts, Packagist et npm maintenant ?'))
            ->setPermission('synchronize', 'ROLE_ADMIN');
    }

    public function configureFields(string $pageName): iterable
    {
        yield IdField::new('id')->onlyOnIndex();
        yield TextField::new('name', 'Paquet')->setColumns(6);
        yield TextField::new('tierLabel', 'Étage')->setColumns(3);
        yield TextField::new('family', 'Famille')->setColumns(3);
        yield TextareaField::new('description', 'Description')->hideOnIndex();
        yield TextField::new('license', 'Licence')->setColumns(3)->hideOnIndex();
        yield TextField::new('branch', 'Branche')->setColumns(3);
        yield TextField::new('repositoryUrl', 'Dépôt')->setColumns(6)->hideOnIndex();
        yield TextField::new('registryLabel', 'Registre')->setColumns(3);
        yield TextField::new('publishedVersion', 'Version publiée')->setColumns(3)->hideOnIndex();
        yield DateTimeField::new('registryCheckedAt', 'Registre interrogé le')->setColumns(3)->hideOnIndex();
        yield TextField::new('verificationLabel', 'Vérifié en réel')->setColumns(3);
        yield TextareaField::new('verificationNote', 'D\'après le README')->hideOnIndex();
        yield IntegerField::new('docPages', 'Pages de docs/')->setColumns(2);
        yield BooleanField::new('readme', 'README')->setColumns(2)->hideOnIndex();
        yield TextField::new('usedByLabel', 'Sites')->setColumns(6)->hideOnIndex();
        yield BooleanField::new('missing', 'Dépôt introuvable')->setColumns(2)->hideOnIndex();
        yield DateTimeField::new('syncedAt', 'Lu le')->setColumns(3)->hideOnIndex();
    }

    #[AdminAction('/synchronize')]
    public function synchronize(): Response
    {
        $report = $this->synchronizer->sync();
        $this->addFlash('success', sprintf(
            'Catalogue relu : %d paquet(s), %d créé(s), %d modifié(s), %d introuvable(s) ; registres : %d réponse(s), %d sans réponse.',
            $report->total(), \count($report->created), \count($report->updated), \count($report->missing), $report->registryAnswered, $report->registryUnreachable,
        ));

        return $this->redirectToIndex();
    }
}
