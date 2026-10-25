<?php

/*
 * The Omniscient website's static export (omnibridge/static): every public
 * page of the site - the routes of src/Controller, enumerated from Symfony's
 * router with the values of their parameters read from the content files -
 * rendered as a visitor by the site's own kernel, for a static host.
 *
 *     bin/omnibridge-static build [--github-pages]   (the whole build: docs, assets, export, check)
 *     vendor/bin/omnibridge-static build static.php --out=public-static [--target=github-pages]
 *
 * Environment:
 *   SITE_URL        the static site's public address (canonical links, sitemap, feeds): an origin, or the
 *                   folder of one it is served under (https://glitchr-studio.github.io/omniscient/) - the
 *                   pages are rendered at the root and omnibridge/static moves every internal address under it
 *   RENDER_ORIGIN   the origin the kernel renders at (SITE_URL's origin by default), rewritten to it in every page
 *   APP_ENV         prod for a deployable build (dev shows the profiler's toolbar)
 *
 * Nothing here needs a database: the catalogue, the guides, the news and the
 * documentation are files (catalog/, docs/, news/, var/docs fetched by
 * app:docs:fetch at the refs of config/documentation.yaml).
 */

use App\Catalogue\Catalogue;
use App\Content\Documentation;
use App\Content\Guides;
use App\Content\News;
use Omnibridge\Static\Console\Cli;
use Omnibridge\Static\Render\RequestRenderer;
use Omnibridge\Static\Route\RouteCollectionProvider;
use Omnibridge\Static\Site;
use Symfony\Component\Dotenv\Dotenv;
use Symfony\Component\HttpFoundation\Request;

return static function (array $host): Site {
    $root = __DIR__;
    if (!isset($host[Cli::HOST_RENDERER])) {
        require_once $root.'/vendor/autoload.php';
        (new Dotenv())->bootEnv($root.'/.env');
    }

    $static = rtrim(getenv('SITE_URL') ?: 'http://localhost:8135', '/');
    // The pages are rendered at the root of the static site's own origin (its absolute links, feeds and
    // robots.txt are then right as written); another RENDER_ORIGIN is rewritten to it in every page. Under
    // a folder (GitHub Pages: /omniscient), the export moves the internal addresses into it.
    $origin = (new Site($static))->origin();
    $render = rtrim(getenv('RENDER_ORIGIN') ?: $origin, '/');

    if (isset($host[Cli::HOST_RENDERER], $host[Cli::HOST_ROUTES], $host[Cli::HOST_CONTAINER])) {
        // bin/console omnibridge:static:build: this kernel's renderer, routes and container.
        $renderer = $host[Cli::HOST_RENDERER];
        $routes = $host[Cli::HOST_ROUTES];
        $container = $host[Cli::HOST_CONTAINER];
    } else {
        // The plain command line: the site's kernel, booted here, rendering every page in one process.
        $kernel = new App\Kernel($_SERVER['APP_ENV'], (bool) $_SERVER['APP_DEBUG']);
        $kernel->boot();
        $container = $kernel->getContainer();
        $renderer = new RequestRenderer(static fn (Request $request) => $kernel->handle($request), $render);
        $routes = $container->get('router')->getRouteCollection();
    }

    // The values of the routes' parameters: the content files.
    $catalogue = $container->get(Catalogue::class);
    $guides = $container->get(Guides::class);
    $news = $container->get(News::class);
    $documentation = $container->get(Documentation::class);

    // A manual's pages, of its current series or of its older ones (out of the sitemap, noindex).
    $manuals = static function (bool $pages, bool $current) use ($documentation): array {
        $rows = [];
        foreach ($documentation->manuals() as $manual) {
            foreach ($manual->series() as $series) {
                if ($manual->isCurrent($series) !== $current) {
                    continue;
                }
                if (!$pages) {
                    $rows[] = ['key' => $manual->key, 'series' => $series];
                    continue;
                }
                foreach ($documentation->pages($manual, $series) as $page) {
                    if ('' !== $page->path) {
                        $rows[] = ['key' => $manual->key, 'series' => $series, 'path' => $page->path];
                    }
                }
            }
        }

        return $rows;
    };
    $keys = static fn (): array => array_map(static fn ($m): array => ['key' => $m->key], array_values($documentation->manuals()));
    $slugs = static fn (array $slugs): array => array_map(static fn (string $slug): array => ['slug' => $slug], $slugs);

    // preg_replace() with its failure seen: never a page cut short.
    $replace = static fn (array $patterns, array $replacements, string $html): string => preg_replace($patterns, $replacements, $html) ?? throw new RuntimeException(preg_last_error_msg());

    return (new Site($static))
        ->provide(new RouteCollectionProvider(
            $routes,
            parameters: [
                'app_family' => static fn (): array => array_values(array_map(static fn ($f): array => ['family' => $f->name], array_filter($catalogue->families(), static fn ($f): bool => $f->hasPage()))),
                'app_package' => static fn (): array => array_values(array_map(static fn ($p): array => ['vendor' => $p->getVendor(), 'name' => $p->getShortName()], $catalogue->all())),
                'app_guide.en' => static fn (): array => $slugs(array_keys($guides->all('en'))),
                'app_guide.fr' => static fn (): array => $slugs(array_keys($guides->all('fr'))),
                'app_news_item.en' => static fn (): array => $slugs($news->slugs('en')),
                'app_news_item.fr' => static fn (): array => $slugs($news->slugs('fr')),
                'app_manual_root' => $keys,
                'app_manual_latest' => $keys,
                'app_manual' => static fn (): array => $manuals(false, true),
                'app_manual_page' => static fn (): array => $manuals(true, true),
            ],
            // The page for unknown addresses is written as 404.html, not as a page of its own.
            exclude: ['app_not_found'],
            actions: ['App\Controller\\'],
        ))
        // The older series of a manual (omnibase 3.x beside 4.x): published, linked from the version
        // selector, not in sitemap.xml - their pages say noindex, the current series is the one indexed.
        ->provide(new RouteCollectionProvider(
            $routes,
            parameters: [
                'app_manual' => static fn (): array => $manuals(false, false),
                'app_manual_page' => static fn (): array => $manuals(true, false),
            ],
            include: ['app_manual', 'app_manual.*', 'app_manual_page', 'app_manual_page.*'],
            noSitemap: ['*'],
        ))
        ->renderWith($renderer)
        ->publicDir($root.'/public')
        // Encore's build (the site's entries and omnibase's, with the chunks their runtime loads).
        ->assets($root.'/public/assets/manifest.json')
        // The search page's index, which assets/search.js fetches: followed, and moved with the base path.
        ->resourceAttributes('data-site-search')
        ->notFound('/not-found')
        // What changes at every request, out of every page: the build is the same from one run to the next.
        ->normalise(static fn (string $html): string => $replace([
            // Symfony's web debug toolbar (dev).
            '#<style nonce="[^"]*">\[id\^="sfwdt"\].*?</style>|<!-- START of Symfony Web Debug Toolbar -->.*?(?=</body>)#s',
            // Content-Security-Policy nonces: a static host sends no such policy.
            '/ nonce="[^"]*"/',
            // The credits' render time (omnibase's signature): a static page was rendered once, at build time.
            '# <span class="ga-credits-time">\[[^\]]*\]</span>#',
        ], ['', '', ''], $html))
        // The credits name the host the page was rendered at, in capitals: the static site's.
        ->normalise(static fn (string $html): string => str_replace(strtoupper((string) parse_url($render, \PHP_URL_HOST)), strtoupper((string) parse_url($origin, \PHP_URL_HOST)), $html))
        // The render origin, as the router writes it, moved to the static site's.
        ->normalise(static fn (string $html): string => $render === $origin ? $html : str_replace([$render, str_replace('/', '\/', $render), rawurlencode($render)], [$origin, str_replace('/', '\/', $origin), rawurlencode($origin)], $html));
};
