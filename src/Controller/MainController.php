<?php

namespace App\Controller;

use App\Catalogue\Catalogue;
use App\Catalogue\Tier;
use App\Content\Documentation;
use App\Content\Guides;
use App\Content\News;
use App\Site\Identity;
use App\Site\Locale;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * The site's own pages: the home page (Omni and its turning suffix, the
 * foundation - the core, the families, the bridges - and apart, optional,
 * the prepared structures), how to support the project, the legal pages
 * and the page for unknown addresses. Every route is localized: English at
 * the root, French under /fr.
 */
class MainController extends AbstractController
{
    #[Route(['en' => '/', 'fr' => '/fr'], name: 'app_index', methods: ['GET'])]
    public function index(Request $request, Catalogue $catalogue, News $news, Guides $guides, Documentation $documentation): Response
    {
        $locale = Locale::of($request);
        $families = [];
        foreach ($catalogue->families() as $name => $family) {
            $own = $family->getOwn();
            if (null === $own || Tier::Family !== $own->getTier() && Tier::Foundation !== $own->getTier()) {
                continue;
            }
            $families[$name] = [
                'name' => $name,
                'suffix' => $family->getSuffix(),
                'phrase' => $family->phrase($locale),
                'package' => $own,
                'members' => \count(array_filter($family->getMembers(), static fn ($p): bool => Tier::Provider === $p->getTier())),
            ];
        }

        // The JavaScript libraries: at the families' level on the home page, a family with no core.
        $libraries = $catalogue->family('libraries');

        $floors = [];
        foreach (Tier::cases() as $tier) {
            $floors[$tier->value] = $catalogue->findByTier($tier);
        }

        return $this->render('client/index.html.twig', [
            'families' => $families,
            'libraries' => null !== $libraries ? ['phrase' => $libraries->phrase($locale), 'count' => \count($libraries->getMembers())] : null,
            'floors' => $floors,
            // The examples on the floors turn: the families' own packages, the prepared structures on their own floor.
            'turning' => array_values(array_map(static fn (array $f): string => $f['package']->getName(), array_filter($families, static fn (array $f): bool => Tier::Family === $f['package']->getTier()))),
            'structures' => array_map(static fn ($package): string => $package->getName(), $floors['component']),
            'counts' => $catalogue->counts() + ['manuals' => \count($documentation->manuals())],
            'posts' => \array_slice($news->all($locale), 0, 3),
            'guides' => $guides->all($locale),
        ]);
    }

    #[Route(['en' => '/support', 'fr' => '/fr/soutenir'], name: 'app_support', methods: ['GET'])]
    public function support(Catalogue $catalogue): Response
    {
        return $this->render('client/support.html.twig', ['counts' => $catalogue->counts()]);
    }

    #[Route(['en' => '/legal', 'fr' => '/fr/mentions-legales'], name: 'app_legal', methods: ['GET'])]
    public function legal(): Response
    {
        return $this->render('client/pages/legal.html.twig');
    }

    #[Route(['en' => '/privacy', 'fr' => '/fr/confidentialite'], name: 'app_privacy', methods: ['GET'])]
    public function privacy(): Response
    {
        return $this->render('client/pages/privacy.html.twig');
    }

    /** robots.txt, with the sitemap the static export writes beside it. */
    #[Route('/robots.txt', name: 'app_robots', defaults: ['_locale' => 'en'], methods: ['GET'])]
    public function robots(Identity $site): Response
    {
        return new Response("User-agent: *\nAllow: /\n\nSitemap: ".$site->absolute('/sitemap.xml')."\n", 200, ['Content-Type' => 'text/plain; charset=UTF-8']);
    }

    /**
     * The page a static host serves for an unknown address (404.html in the export), in English: its
     * route has no language of its own, and the build renders it after pages of both.
     */
    #[Route('/not-found', name: 'app_not_found', defaults: ['_locale' => 'en'], methods: ['GET'])]
    public function notFound(): Response
    {
        return $this->render('client/pages/not_found.html.twig', [], new Response('', Response::HTTP_NOT_FOUND));
    }
}
