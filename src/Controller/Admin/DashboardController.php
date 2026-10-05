<?php

namespace App\Controller\Admin;

use Base\Admin\Config\MenuItem;
use Base\Admin\Controller\AbstractDashboardController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * The back office (plan 0 §6.5: Contenus · Métier · Outils · Réglages - no
 * shop and no mailbox here): first the state of the catalogue and what has
 * no documentation yet, then the announcements, the packages and their
 * manuals, the accounts, the settings. transparent.js opens it as a
 * website-in-website over the site.
 */
class DashboardController extends AbstractDashboardController
{
    #[Route('/admin', name: 'admin')]
    public function index(): Response
    {
        return parent::index();
    }

    public function configureMenuItems(): iterable
    {
        if (!$this->isGranted('ROLE_ADMIN')) {
            return;
        }
        yield MenuItem::section('Réglages', 'fa-solid fa-gears');
        yield MenuItem::linkToRoute('admin_settings', [], 'Réglages', 'fa-solid fa-sliders');
        if ($this->isGranted('ROLE_SUPERADMIN')) {
            yield MenuItem::linkToRoute('admin_apikey', [], 'Clés API', 'fa-solid fa-key');
        }
        yield MenuItem::linkToRoute('admin_trash', [], 'Corbeille', 'fa-solid fa-trash-can');
    }

    public function configureWidgetItems(): iterable
    {
        if (!$this->isGranted('ROLE_STAFF')) {
            return;
        }

        yield MenuItem::block('app_catalogue', 'Catalogue', 'fa-solid fa-cubes')->setSize(4);
        yield MenuItem::block('app_undocumented', 'Paquets sans documentation', 'fa-solid fa-file-circle-question')->setSize(4);

        if (!$this->isGranted('ROLE_ADMIN')) {
            return;
        }

        yield MenuItem::section('Contenus', 'fa-solid fa-pen-nib')->setSubItems([
            MenuItem::linkToCrud(\Base\Blog\Entity\Post::class, 'Articles', 'fa-solid fa-feather'),
            MenuItem::linkToCrud(\Base\Entity\Layout\TextOverride::class, 'Textes du site', 'fa-solid fa-font'),
        ]);

        yield MenuItem::section('Métier', 'fa-solid fa-cubes')->setSubItems([
            MenuItem::linkToCrud(\App\Entity\Package::class, 'Paquets', 'fa-solid fa-cube'),
            MenuItem::linkToRoute('admin_manuals', [], 'Manuels', 'fa-solid fa-book'),
            MenuItem::linkToCrud(\App\Entity\Follow::class, 'Suivis', 'fa-solid fa-bell'),
        ]);

        yield MenuItem::section('Comptes', 'fa-solid fa-users')->setSubItems([
            MenuItem::linkToCrud(\App\Entity\User::class, 'Utilisateurs', 'fa-solid fa-user'),
            MenuItem::linkToCrud(\Base\Entity\User\Permission::class, 'Permissions', 'fa-solid fa-lock'),
            MenuItem::linkToCrud(\Base\Entity\User\Complaint::class, 'Signalements', 'fa-solid fa-hand'),
        ]);
    }
}
