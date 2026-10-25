<?php

namespace App\Controller;

use App\Catalogue\Catalogue;
use App\Content\Documentation;
use App\Content\Manual;
use App\Site\Locale;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Each package's manual: /docs/<key>/<series>/<page>, read from its own
 * repository's docs/ at the commit config/documentation.yaml pins. Each
 * package has its own series; /docs/<key> and /docs/<key>/latest are
 * redirections to its default series, never canonical content. An older
 * series than the default is published with a notice and kept out of search
 * engines' indexes (noindex, out of the sitemap).
 */
class DocumentationController extends AbstractController
{
    private const KEY = '[a-z0-9][a-z0-9-]*';
    private const SERIES = '[0-9a-z][a-z0-9.-]*';

    public function __construct(
        private readonly Documentation $documentation,
        private readonly Catalogue $catalogue,
    ) {
    }

    #[Route(['en' => '/docs/{key}', 'fr' => '/fr/docs/{key}'], name: 'app_manual_root', requirements: ['key' => self::KEY], methods: ['GET'])]
    public function root(string $key): Response
    {
        return $this->redirectToRoute('app_manual', ['key' => $key, 'series' => $this->manual($key)->default], Response::HTTP_MOVED_PERMANENTLY);
    }

    #[Route(['en' => '/docs/{key}/latest', 'fr' => '/fr/docs/{key}/latest'], name: 'app_manual_latest', requirements: ['key' => self::KEY], methods: ['GET'], priority: 10)]
    public function latest(string $key): Response
    {
        return $this->redirectToRoute('app_manual', ['key' => $key, 'series' => $this->manual($key)->default], Response::HTTP_FOUND);
    }

    #[Route(['en' => '/docs/{key}/{series}', 'fr' => '/fr/docs/{key}/{series}'], name: 'app_manual', requirements: ['key' => self::KEY, 'series' => self::SERIES], methods: ['GET'])]
    public function manualHome(Request $request, string $key, string $series): Response
    {
        return $this->page($request, $key, $series, '');
    }

    #[Route(['en' => '/docs/{key}/{series}/{path}', 'fr' => '/fr/docs/{key}/{series}/{path}'], name: 'app_manual_page', requirements: ['key' => self::KEY, 'series' => self::SERIES, 'path' => '[a-z0-9][a-z0-9/-]*'], methods: ['GET'])]
    public function page(Request $request, string $key, string $series, string $path): Response
    {
        $manual = $this->manual($key);
        if (!$manual->has($series)) {
            throw $this->createNotFoundException(sprintf('%s has no series "%s".', $key, $series));
        }
        $page = $this->documentation->page($manual, $series, $path);
        if (null === $page && '' !== $path) {
            throw $this->createNotFoundException(sprintf('%s %s has no page "%s".', $key, $series, $path));
        }
        $locale = Locale::of($request);
        $url = fn (string $to, ?string $fragment): string => $this->generateUrl('' === $to ? 'app_manual' : 'app_manual_page', array_filter(['key' => $key, 'series' => $series, 'path' => $to, '_locale' => $locale], static fn ($v): bool => '' !== $v)).(null !== $fragment ? '#'.$fragment : '');

        // The version selector: the same page in each series when it has it, else that series' home.
        $versions = [];
        foreach ($manual->series() as $other) {
            $same = '' !== $path && null !== $this->documentation->page($manual, $other, $path);
            $versions[$other] = $this->generateUrl($same ? 'app_manual_page' : 'app_manual', array_filter(['key' => $key, 'series' => $other, 'path' => $same ? $path : '', '_locale' => $locale], static fn ($v): bool => '' !== $v));
        }

        $pages = $this->documentation->pages($manual, $series);
        $index = null !== $page ? array_search($page, $pages, false) : false;

        return $this->render('client/docs/manual.html.twig', [
            'manual' => $manual,
            'series' => $series,
            'versions' => $versions,
            // An older series: no canonical link to itself and noindex - its pages differ from the
            // current series' (a canonical pointing there would be ignored), and a search should land
            // on the current series; static.php keeps them out of the sitemap.
            'canonical' => $manual->isCurrent($series),
            'source' => $manual->versions[$series],
            'commit' => $this->documentation->commit($manual, $series),
            'package' => $this->catalogue->find($manual->package),
            'page' => $page,
            'rendered' => null !== $page ? $this->documentation->render($manual, $series, $page, $url) : null,
            'tree' => $this->documentation->tree($manual, $series),
            'home' => $this->documentation->home($manual, $series),
            'previous' => false !== $index && $index > 0 ? $pages[$index - 1] : null,
            'next' => false !== $index && isset($pages[$index + 1]) ? $pages[$index + 1] : null,
        ]);
    }

    private function manual(string $key): Manual
    {
        return $this->documentation->manual($key) ?? throw $this->createNotFoundException(sprintf('No manual "%s".', $key));
    }
}
