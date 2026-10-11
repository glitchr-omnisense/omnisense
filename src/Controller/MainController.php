<?php

namespace App\Controller;

use App\Catalogue\Families;
use App\Catalogue\Tier;
use App\Repository\PackageRepository;
use App\Sponsor\GitHubSponsors;
use Base\Blog\Repository\PostRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * The site's own pages: the home page (Omni and its turning suffix, the
 * floors of the system, the way to the documentation), how to support it,
 * the legal pages. The sites built on the packages are not listed here:
 * /demos, which listed them, sends to the documentation. The catalogue is CatalogueController's, the manuals
 * omnibase/docs', the announcements omnibase/blog's.
 */
class MainController extends AbstractController
{
    #[Route('/', name: 'app_index')]
    public function index(PackageRepository $packages, PostRepository $posts): Response
    {
        // The families that exist are the ones found in a repository; the
        // order they turn in, and their sentence, are the site's.
        $families = [];
        foreach (Families::ordered($packages->findFamilies()) as $name => $package) {
            $members = $packages->findFamily($name);
            $families[$name] = [
                'name' => $name,
                'suffix' => Families::suffix($name),
                'package' => $package,
                'members' => \count($members) - 1,
            ];
        }

        $floors = [];
        foreach (Tier::cases() as $tier) {
            $floors[$tier->value] = $packages->findByTier($tier);
        }

        return $this->render('client/index.html.twig', [
            'families' => $families,
            'floors' => $floors,
            'counts' => $packages->counts(),
            'posts' => $posts->findLatest(3),
        ]);
    }

    /** The page that listed the sites is gone (Marco, 2026-10-10): the documentation is what is shown. */
    #[Route('/demos', name: 'app_demos')]
    public function demos(): Response
    {
        return $this->redirectToRoute('wikidoc_index', [], Response::HTTP_MOVED_PERMANENTLY);
    }

    #[Route('/support', name: 'app_support')]
    public function support(GitHubSponsors $github, PackageRepository $packages): Response
    {
        return $this->render('client/support.html.twig', [
            'sponsors' => $github->fetch(),
            'counts' => $packages->counts(),
        ]);
    }

    #[Route('/legal', name: 'app_legal')]
    public function legal(): Response
    {
        return $this->render('client/pages/legal.html.twig');
    }

    #[Route('/privacy', name: 'app_privacy')]
    public function privacy(): Response
    {
        return $this->render('client/pages/privacy.html.twig');
    }
}
