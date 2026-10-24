<?php

namespace App\Twig;

use App\Catalogue\Families;
use App\Catalogue\PackageSearch;
use App\Site\Identity;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Routing\Exception\ExceptionInterface as RoutingException;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Twig\Extension\AbstractExtension;
use Twig\TwigFilter;
use Twig\TwigFunction;

/**
 * What the templates ask beyond the controllers' variables: a name marked
 * as the home page shows it, a family's suffix, a package's search
 * haystack, and the current page in each language (hreflang, the language
 * link, the canonical address).
 */
class CatalogueExtension extends AbstractExtension
{
    /** The site's languages, the default first (English at the root, French under /fr). */
    public const LOCALES = ['en', 'fr'];

    public function __construct(
        private readonly RequestStack $requests,
        private readonly UrlGeneratorInterface $urls,
        private readonly Identity $site,
    ) {
    }

    public function getFilters(): array
    {
        return [
            new TwigFilter('omni_mark', self::mark(...), ['is_safe' => ['html']]),
        ];
    }

    public function getFunctions(): array
    {
        return [
            new TwigFunction('family_suffix', Families::suffix(...)),
            new TwigFunction('search_haystack', PackageSearch::haystack(...)),
            new TwigFunction('page_alternates', $this->alternates(...)),
            new TwigFunction('site_locales', static fn (): array => self::LOCALES),
            // An address at the static site's public origin (SITE_URL), whatever host renders the page.
            new TwigFunction('site_url', fn (string $route, array $parameters = []): string => $this->site->absolute($this->urls->generate($route, $parameters))),
        ];
    }

    /**
     * A package name or a floor's pattern with its parts told apart: "omni"
     * in grey, the domain after it in blue - glitchr/omni<b>bank</b>,
     * omni<b>bank</b>/powens, omni<b>&lt;domain&gt;</b>/&lt;service&gt;.
     * assets/controllers/examples_controller.js marks the names it turns in
     * the same way.
     */
    public static function mark(string $name): string
    {
        return implode('/', array_map(static fn (string $part): string => 1 === preg_match('/^omni(.+)$/', $part, $match)
            ? '<span class="om-mark-omni">omni</span><b class="om-mark-domain">'.htmlspecialchars($match[1]).'</b>'
            : htmlspecialchars($part), explode('/', $name)));
    }

    /**
     * The current page's address in each language it exists in, absolute:
     * the same route under its other locale, with the same parameters.
     *
     * @param list<string>|null $only the languages the page is written in (a piece of news may be in one)
     *
     * @return array<string, string> locale => URL
     */
    public function alternates(?array $only = null): array
    {
        $request = $this->requests->getMainRequest();
        $route = (string) $request?->attributes->get('_route', '');
        if ('' === $route || null === $request) {
            return [];
        }
        // omnibase's router answers a localized route by its canonical name (app_package, not app_package.fr).
        $canonical = (string) preg_replace('/\.(?:'.implode('|', self::LOCALES).')$/', '', $route);
        $params = (array) $request->attributes->get('_route_params', []);
        unset($params['_locale'], $params['_canonical_route']);

        $out = [];
        foreach (self::LOCALES as $locale) {
            try {
                $out[$locale] = $this->site->absolute($this->urls->generate($canonical, ['_locale' => $locale] + $params));
            } catch (RoutingException) {
            }
        }
        // A route that is not localized has the same address in every language: no alternates.
        if (\count(array_unique($out)) < \count($out)) {
            return [];
        }

        return null === $only ? $out : array_intersect_key($out, array_flip($only));
    }
}
