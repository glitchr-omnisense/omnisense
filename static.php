<?php

/*
 * The Omnisense website's static export (omnibridge/static): every public
 * page of the site - the routes of src/Controller, enumerated from Symfony's
 * router with the values of their parameters read from the content files -
 * rendered as a visitor by the site's own kernel, for a static host.
 *
 *     bin/omnibridge-static build [--github-pages] [--full] [--only=<pattern>,...]   (the whole build: docs, assets, export, check)
 *     vendor/bin/omnibridge-static build static.php --out=public-static [--target=github-pages]
 *
 * Incremental: the inputs each page is rendered from are declared below (Site::dependsOn()), the answers
 * kept in var/static-cache; a page whose inputs did not change is not rendered again.
 *
 * Environment:
 *   SITE_URL        the static site's public address (canonical links, sitemap, feeds): an origin, or the
 *                   folder of one it is served under (https://glitchr-omnisense.github.io/omnisense/) - the
 *                   pages are rendered at the root and omnibridge/static moves every internal address under it
 *   RENDER_ORIGIN   the origin the kernel renders at (SITE_URL's origin by default), rewritten to it in every page
 *   APP_ENV         prod for a deployable build (dev shows the profiler's toolbar)
 *
 * Nothing here needs a database: the catalogue, the guides, the news and the
 * documentation are files (catalog/, docs/, news/, var/docs fetched by
 * app:docs:fetch at the refs of config/documentation.yaml).
 */

use App\Catalogue\Catalogue;
use App\Catalogue\Compatibility;
use App\Content\Documentation;
use App\Content\Guides;
use App\Content\News;
use Omnibridge\Static\Console\Cli;
use Omnibridge\Static\Render\RequestRenderer;
use Omnibridge\Static\Route\RouteCollectionProvider;
use Omnibridge\Static\Site;
use Omnibridge\Static\StaticPage;
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
    // a folder (GitHub Pages: /omnisense), the export moves the internal addresses into it.
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
    $compatibility = $container->get(Compatibility::class);

    // What a page is rendered from, for an incremental export (omnibridge/static's Site::dependsOn()): a page
    // whose inputs did not change is taken from var/static-cache instead of being rendered again. Nothing
    // checks these lists - an input left out gives a stale page - so a page is declared only where its
    // inputs are known for sure; any other is rendered every time. CI compares the export with a --full one.
    //
    // A value (a commit, a configuration's entry) enters as a label and the SHA-256 of its JSON: omnibridge/static
    // takes a string holding *, ? or [ for a glob pattern, which a JSON value would be.
    $value = static fn (string $label, mixed $data): string => $label.' '.hash('sha256', json_encode($data, \JSON_THROW_ON_ERROR | \JSON_UNESCAPED_SLASHES | \JSON_UNESCAPED_UNICODE));
    // The environment the kernel renders in: every variable the .env files define or the configuration reads
    // (%env(NAME)%), with its value in this process - but secrets, which change no page (and APP_SECRET, a
    // cache seed). SITE_URL and RENDER_ORIGIN, read by this file, too.
    $environment = static function () use ($root): array {
        $names = ['APP_ENV' => true, 'APP_DEBUG' => true, 'SITE_URL' => true, 'RENDER_ORIGIN' => true];
        foreach (glob($root.'/.env*') ?: [] as $file) {
            preg_match_all('/^\s*(?:export\s+)?([A-Z][A-Z0-9_]*)=/m', (string) file_get_contents($file), $m);
            $names += array_fill_keys($m[1], true);
        }
        $config = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root.'/config', FilesystemIterator::SKIP_DOTS));
        foreach ($config as $file) {
            if ($file instanceof SplFileInfo && $file->isFile()) {
                preg_match_all('/%env\((?:[^)%]*:)?([A-Z][A-Z0-9_]*)\)%/', (string) file_get_contents($file->getPathname()), $m);
                $names += array_fill_keys($m[1], true);
            }
        }
        $values = [];
        foreach (array_keys($names) as $name) {
            if (1 !== preg_match('/SECRET|PASSWORD|TOKEN|PRIVATE|_KEY$|_KEY_|^KEY/', $name)) {
                $read = $_SERVER[$name] ?? $_ENV[$name] ?? getenv($name);
                $values[$name] = false === $read ? null : $read;
            }
        }
        ksort($values);

        return $values;
    };
    // Every page: the code that renders it, its templates and translations, the configuration (but
    // documentation.yaml, compatibility.yaml, apis.yaml and policies.json, read below by the pages that print
    // them), the front end's manifests (the
    // files themselves are copied, not rendered), the locked packages, the .env files and the environment;
    // then what each manual's page knows of the other manuals - its key, package and default series, for the
    // links to it - and of the catalogue - each family and the names of its packages, for the links to them
    // (Catalogue::skeleton()) - and the year the credits print, the timezone and the ICU version dates are
    // written with. What a package's entry says is in its family's file (catalog/<family>.yaml): an input of
    // the pages that print it only.
    $shared = [$root.'/src', $root.'/templates', $root.'/translations', $root.'/composer.lock', $root.'/public/assets/manifest.json', $root.'/public/assets/entrypoints.json'];
    foreach (glob($root.'/config/*') ?: [] as $entry) {
        if (!in_array(basename($entry), ['documentation.yaml', 'compatibility.yaml', 'apis.yaml', 'policies.json', 'reference.php'], true)) {
            $shared[] = $entry;
        }
    }
    // The star count every page's header shows, where the configuration puts it (config/, or the tests' fixtures).
    $shared[] = (string) $container->getParameter('app.github_file');
    foreach (['.env', '.env.local', '.env.'.$_SERVER['APP_ENV'], '.env.'.$_SERVER['APP_ENV'].'.local'] as $file) {
        $shared[] = is_file($root.'/'.$file) ? $root.'/'.$file : $value('absent', $file);
    }
    $shared[] = $value('environment', $environment());
    $shared[] = $value('manuals', array_map(static fn ($m): array => [$m->key, $m->package, $m->default, $m->series()], array_values($documentation->manuals())));
    $shared[] = $value('catalogue', $catalogue->skeleton());
    $shared[] = $value('clock', [date('Y'), date_default_timezone_get(), defined('INTL_ICU_VERSION') ? INTL_ICU_VERSION : null]);
    // A manual's pages (its one page, its API reference, its pages' former addresses): its entry in
    // config/documentation.yaml, each series' folder in var/docs (the docs/ files and the PHP sources, at the
    // pinned commit) and the commit app:docs:fetch recorded, its package's family file (the package's entry, the
    // family's own package and members its header and side list print) and its row of the compatibility data.
    $manualInputs = static function (mixed $key) use ($documentation, $catalogue, $compatibility, $value): ?array {
        $manual = is_string($key) ? $documentation->manual($key) : null;
        if (null === $manual) {
            return null;
        }
        $inputs = [$value('manual', [$manual->key, $manual->package, $manual->repository, $manual->default, $manual->versions, $manual->website])];
        foreach ($manual->series() as $series) {
            $inputs[] = $documentation->folder($manual, $series) ?? $value('not fetched', [$manual->key, $series]);
            $inputs[] = $value('commit', [$manual->key, $series, $documentation->commit($manual, $series)]);
        }
        $package = $catalogue->find($manual->package);
        $file = null !== $package && null !== $package->getFamily() ? $catalogue->fileOf($package->getFamily()) : null;
        $inputs[] = $file ?? $value('not in the catalogue', $manual->package);
        $row = null !== $package ? $compatibility->of($package) : null;
        if (null !== $row) {
            unset($row['package']); // the catalogue's, in the shared inputs
        }
        $inputs[] = $value('compatibility', $row);

        return $inputs;
    };
    // The folders the content services read (the tests' fixtures in the test environment).
    $newsDir = rtrim((string) $container->getParameter('app.news_dir'), '/');
    $guidesDir = rtrim((string) $container->getParameter('app.guides_dir'), '/');
    $catalogDir = rtrim((string) $container->getParameter('app.catalog_dir'), '/');
    $compatibilityFile = (string) $container->getParameter('app.compatibility_file');
    $documentationFile = (string) $container->getParameter('app.documentation_file');
    // The API cards' data (config/apis.yaml) and the usage policies the providers declare (config/policies.json).
    $apiFiles = [(string) $container->getParameter('app.apis_file'), (string) $container->getParameter('app.policies_file')];
    // What reads every manual (the documentation's entry, the search index): each one's inputs.
    $everyManual = static function () use ($documentation, $manualInputs): array {
        $inputs = [];
        foreach (array_keys($documentation->manuals()) as $key) {
            array_push($inputs, ...($manualInputs((string) $key) ?? []));
        }

        return $inputs;
    };
    // A family's page, or the redirection to its own package's manual (the manual: in the shared inputs).
    $familyInputs = static function (mixed $name) use ($catalogue, $documentation): ?array {
        $family = is_string($name) ? $catalogue->family($name) : null;
        if (null === $family) {
            return null;
        }
        $own = $family->getOwn();
        $file = $catalogue->fileOf($name);

        return null !== $own && null !== $documentation->manualOf($own->getName()) ? [] : (null !== $file ? [$file] : null);
    };
    // A package's page lists what it requires and what requires it (any family): the catalogue. With a manual,
    // a redirection to it (in the shared inputs).
    $packageInputs = static function (array $parameters) use ($catalogue, $documentation, $catalogDir): ?array {
        $vendor = $parameters['vendor'] ?? null;
        $name = $parameters['name'] ?? null;
        if (!is_string($vendor) || !is_string($name)) {
            return null;
        }
        $package = $catalogue->find($vendor.'/'.$name) ?? $catalogue->find('@'.$vendor.'/'.$name);

        return null !== $package && null !== $documentation->manualOf($package->getName()) ? [] : [$catalogDir];
    };
    $pageInputs = static function (StaticPage $page) use ($newsDir, $guidesDir, $catalogDir, $compatibilityFile, $documentationFile, $apiFiles, $manualInputs, $everyManual, $familyInputs, $packageInputs): ?array {
        // 404.html: its template and the shared inputs.
        if (StaticPage::NOT_FOUND === $page->role) {
            return [];
        }
        $parameters = $page->parameters;

        return match (explode('.', (string) $page->route)[0]) {
            'app_manual', 'app_manual_page', 'app_manual_api' => $manualInputs($parameters['key'] ?? null),
            // A redirection to the default series: in the shared inputs.
            'app_manual_root', 'app_manual_latest' => [],
            // A piece of news, in each language it is written in (its alternates): news/<date>-<slug>.<locale>.md.
            'app_news_item' => is_string($parameters['slug'] ?? null) ? [$newsDir.'/*-'.$parameters['slug'].'.*.md'] : null,
            // The list and the feeds show every item.
            'app_news', 'app_news_atom', 'app_news_rss' => [$newsDir],
            // A guide lists the guides of its language and has its translation for alternate; the Install guide
            // ends on every family's releases.
            'app_guide' => 'install' === ($parameters['slug'] ?? null) ? [$guidesDir, $catalogDir] : [$guidesDir],
            'app_family' => $familyInputs($parameters['family'] ?? null),
            'app_package' => $packageInputs($parameters),
            // The home: the families and floors, the counts, the latest news and the guides.
            'app_index' => [$catalogDir, $newsDir, $guidesDir],
            // The catalogue's list; the counts.
            'app_packages', 'app_support' => [$catalogDir],
            // The API cards, the wanted list and the policies' table: config/apis.yaml and the declared policies,
            // joined to the catalogue (App\Catalogue\Api\Directory).
            'app_apis', 'app_contribute', 'app_api_policies' => [$catalogDir, ...$apiFiles],
            // The dashboard: every package's row of the compatibility data.
            'app_compatibility' => [$catalogDir, $compatibilityFile],
            // The documentation's entry (every manual, its family's phrase, its number of pages) and the search
            // index (the guides, the news, the catalogue, every manual's pages).
            'app_docs' => [$catalogDir, $documentationFile, ...$everyManual()],
            'app_search_index' => [$catalogDir, $documentationFile, $newsDir, $guidesDir, ...$everyManual()],
            // Templates and the shared inputs only.
            'app_search', 'app_legal', 'app_privacy', 'app_robots', 'app_not_found' => [],
            // A route added later, or an address found only by following a link (no route): rendered every time.
            default => null,
        };
    };

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
                // Each page's former address - but a path a route of the site takes (Documentation::RESERVED).
                foreach ($documentation->formerAddresses($manual, $series) as $path) {
                    $rows[] = ['key' => $manual->key, 'series' => $series, 'path' => $path];
                }
            }
        }

        return $rows;
    };
    // A PHP package's API reference, of its current series or of its older ones.
    $apis = static function (bool $current) use ($documentation): array {
        $rows = [];
        foreach ($documentation->manuals() as $manual) {
            foreach ($manual->series() as $series) {
                if ($manual->isCurrent($series) === $current && null !== $documentation->api($manual, $series)) {
                    $rows[] = ['key' => $manual->key, 'series' => $series];
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
                'app_family' => static fn (): array => array_values(array_map(static fn ($f): array => ['family' => $f->name], array_filter($catalogue->families(), static fn ($f): bool => $f->hasPage() || 'libraries' === $f->name))),
                'app_package' => static fn (): array => array_values(array_map(static fn ($p): array => ['vendor' => $p->getVendor(), 'name' => $p->getShortName()], $catalogue->all())),
                'app_guide.en' => static fn (): array => $slugs(array_keys($guides->all('en'))),
                'app_guide.fr' => static fn (): array => $slugs(array_keys($guides->all('fr'))),
                'app_news_item.en' => static fn (): array => $slugs($news->slugs('en')),
                'app_news_item.fr' => static fn (): array => $slugs($news->slugs('fr')),
                'app_manual_root' => $keys,
                'app_manual_latest' => $keys,
                'app_manual' => static fn (): array => $manuals(false, true),
                // Each page's former address: a redirection to its part of the manual's one page.
                'app_manual_page' => static fn (): array => $manuals(true, true),
                'app_manual_api' => static fn (): array => $apis(true),
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
                'app_manual_api' => static fn (): array => $apis(false),
            ],
            include: ['app_manual', 'app_manual.*', 'app_manual_page', 'app_manual_page.*', 'app_manual_api', 'app_manual_api.*'],
            noSitemap: ['*'],
        ))
        ->renderWith($renderer)
        // The inputs above, and where the answers are kept between exports (kept between CI runs by the
        // workflow's cache). --full renders everything; --only=<pattern> renders what it names whatever the cache holds.
        ->dependsOn($shared, $pageInputs)
        ->cache($root.'/var/static-cache')
        // The cache's record also holds every PHP file the export loaded: a changed one (vendor, src) renders
        // everything. But the kernel's compiled container and Twig's compiled templates, written again by every
        // fresh boot (bin/omnibridge-static clears var/cache, a CI run starts without it; the container carries
        // its build time): their sources (config/, templates/, src/) are inputs of every page above.
        ->ignoreLoaded($root.'/var/cache/*')
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
