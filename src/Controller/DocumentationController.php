<?php

namespace App\Controller;

use App\Catalogue\Catalogue;
use App\Catalogue\Compatibility;
use App\Content\Documentation;
use App\Content\Manual;
use App\Site\Locale;
use Omnibridge\Static\StaticResponse;
use Omnidocs\OnePage;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Each package's documentation, one page per package and series:
 * /docs/<key>/<series> holds the whole of its docs/ folder - the home, then
 * every page in reading order (omnidocs' OnePage) - read from its own
 * repository at the commit config/documentation.yaml pins, beside a sidebar
 * whose table of contents sticks and follows the reading; its header says
 * what the catalogue knew of the package (CI, live check, version,
 * repository), a family's core lists its providers. /docs/<key>/<series>/api
 * is a PHP package's API reference, read from its sources.
 *
 * The former address of each page, /docs/<key>/<series>/<page>, redirects
 * to its part of the one page, the visitor's #anchor carried over. /docs/<key>
 * and /docs/<key>/latest redirect to the default series. An older series is
 * published with a notice, out of search engines' indexes.
 */
class DocumentationController extends AbstractController
{
    private const KEY = '[a-z0-9][a-z0-9-]*';
    private const SERIES = '[0-9a-z][a-z0-9.-]*';

    public function __construct(
        private readonly Documentation $documentation,
        private readonly Catalogue $catalogue,
        private readonly Compatibility $compatibility,
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
        $manual = $this->series($key, $series);
        $locale = Locale::of($request);
        $url = $this->generateUrl('app_manual', ['key' => $key, 'series' => $series, '_locale' => $locale]);

        return $this->onePage($manual, $series, $this->documentation->onePage($manual, $series, $url), $locale, false);
    }

    #[Route(['en' => '/docs/{key}/{series}/api', 'fr' => '/fr/docs/{key}/{series}/api'], name: 'app_manual_api', requirements: ['key' => self::KEY, 'series' => self::SERIES], methods: ['GET'], priority: 5)]
    public function api(Request $request, string $key, string $series): Response
    {
        $manual = $this->series($key, $series);
        $version = $this->documentation->api($manual, $series) ?? throw $this->createNotFoundException(sprintf('%s %s has no API reference.', $key, $series));
        $locale = Locale::of($request);
        $url = $this->generateUrl('app_manual_api', ['key' => $key, 'series' => $series, '_locale' => $locale]);

        return $this->onePage($manual, $series, new OnePage($version, $this->documentation->linker($manual, $series, $url), $url), $locale, true);
    }

    /** A page's former address: its part of the one page, the visitor's #anchor carried over (prefixed as the part's are). */
    #[Route(['en' => '/docs/{key}/{series}/{path}', 'fr' => '/fr/docs/{key}/{series}/{path}'], name: 'app_manual_page', requirements: ['key' => self::KEY, 'series' => self::SERIES, 'path' => '[a-z0-9][a-z0-9/-]*'], methods: ['GET'])]
    public function page(Request $request, string $key, string $series, string $path): Response
    {
        $manual = $this->series($key, $series);
        if (!\in_array($path, $this->documentation->formerAddresses($manual, $series), true)) {
            throw $this->createNotFoundException(sprintf('%s %s has no page "%s".', $key, $series, $path));
        }
        $url = $this->generateUrl('app_manual', ['key' => $key, 'series' => $series, '_locale' => Locale::of($request)]);
        $one = $this->documentation->onePage($manual, $series, $url);
        $redirect = $one?->redirects()[$path] ?? throw $this->createNotFoundException();

        return new RedirectResponse($url.'#'.$redirect['anchor'], Response::HTTP_MOVED_PERMANENTLY, [StaticResponse::FRAGMENT_HEADER => $redirect['prefix']]);
    }

    private function onePage(Manual $manual, string $series, ?OnePage $one, string $locale, bool $api): Response
    {
        $package = $this->catalogue->find($manual->package);
        $family = null !== $package && null !== $package->getFamily() ? $this->catalogue->family($package->getFamily()) : null;
        // The version selector: the same page in each series.
        $versions = [];
        foreach ($manual->series() as $other) {
            $versions[$other] = $this->generateUrl($api && null !== $this->documentation->api($manual, $other) ? 'app_manual_api' : 'app_manual', ['key' => $manual->key, 'series' => $other, '_locale' => $locale]);
        }
        // A family's own package lists the family's other packages, each straight to its manual.
        $members = null !== $family && $family->getOwn() === $package ? $family->getMembers() : [];

        return $this->render('client/docs/manual.html.twig', [
            'manual' => $manual,
            'series' => $series,
            'versions' => $versions,
            'canonical' => $manual->isCurrent($series),
            'source' => $manual->versions[$series],
            'commit' => $this->documentation->commit($manual, $series),
            'package' => $package,
            'family' => $family,
            'members' => $members,
            'requires' => null !== $package ? array_values(array_filter(array_map($this->catalogue->find(...), $package->getRequires()))) : [],
            'compat' => null !== $package ? $this->compatibility->of($package) : null,
            'one' => $one,
            'api' => $api,
            'has_api' => null !== $this->documentation->api($manual, $series),
        ]);
    }

    private function series(string $key, string $series): Manual
    {
        $manual = $this->manual($key);
        if (!$manual->has($series)) {
            throw $this->createNotFoundException(sprintf('%s has no series "%s".', $key, $series));
        }

        return $manual;
    }

    private function manual(string $key): Manual
    {
        return $this->documentation->manual($key) ?? throw $this->createNotFoundException(sprintf('No manual "%s".', $key));
    }
}
