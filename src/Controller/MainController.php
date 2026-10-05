<?php

namespace App\Controller;

use App\Catalogue\Families;
use App\Catalogue\Sites;
use App\Catalogue\Tier;
use App\Repository\PackageRepository;
use App\Sponsor\GitHubSponsors;
use Base\Blog\Repository\PostRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * The site's own pages: the home page (Omni and its turning suffix, the
 * floors of the system), the sites built on it, how to support it, the
 * legal pages. The catalogue is CatalogueController's, the manuals
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
            'sites' => \count(Sites::all()),
            'posts' => $posts->findLatest(3),
        ]);
    }

    #[Route('/demos', name: 'app_demos')]
    public function demos(PackageRepository $packages): Response
    {
        $sites = [];
        foreach (Sites::all() as $slug => $site) {
            $site['packages'] = $packages->findUsedBy($slug);
            $sites[$site['kind']][] = $site;
        }

        return $this->render('client/demos.html.twig', [
            'groups' => array_filter([
                Sites::CLIENT => $sites[Sites::CLIENT] ?? [],
                Sites::PRODUCT => $sites[Sites::PRODUCT] ?? [],
                Sites::DEMO => $sites[Sites::DEMO] ?? [],
            ]),
        ]);
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
