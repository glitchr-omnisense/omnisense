<?php

namespace App\Tests\Functional;

/** The site's pages as a visitor - and the static export - reads them, in both languages. */
final class PagesTest extends SiteTestCase
{
    public function testTheHomePageShowsTheFoundationFromTheCatalogue(): void
    {
        $html = $this->get('/');
        self::assertStringContainsString('<html', $html);
        self::assertStringContainsString('lang="en"', $html);
        self::assertStringContainsString('Take payments and sell, whatever the provider.', $html, 'the family\'s phrase, from catalog/omnitrade.yaml');
        self::assertStringContainsString('href="/families/omnitrade"', $html);
        self::assertStringContainsString('The first news', $html);
        self::assertStringContainsString('href="/guides/getting-started"', $html, 'the guides of docs/en');
        // The language alternates and the canonical address, at SITE_URL.
        self::assertStringContainsString('<link rel="canonical" href="http://localhost:8135/">', $html);
        self::assertStringContainsString('<link rel="alternate" hreflang="fr" href="http://localhost:8135/fr">', $html);

        $fr = $this->get('/fr');
        self::assertStringContainsString('lang="fr"', $fr);
        self::assertStringContainsString('Encaisser et vendre, quel que soit le prestataire.', $fr);
        self::assertStringContainsString('href="/fr/familles/omnitrade"', $fr);
        self::assertStringContainsString('La première actualité', $fr);
    }

    public function testThePackageSearchListsEveryPackage(): void
    {
        $html = $this->get('/packages');
        foreach (['omniscient/omnitrade', 'omnitrade/stripe', 'omnitrade/paypal', 'omniscient/omnibase', 'omnibase/blog', 'omnibridge/static'] as $name) {
            self::assertStringContainsString('data-name="'.$name.'"', $html, $name);
        }
        self::assertStringContainsString('Not tested live', $html, 'omnitrade/paypal\'s README says so');
        self::assertStringNotContainsString('?kind=', $html, 'a static page has no query: the filters are in the fragment');
        self::assertStringContainsString('href="/fr/paquets"', $this->get('/fr/paquets'));
    }

    public function testAPackagePageSaysWhatItsCatalogueEntrySays(): void
    {
        $html = $this->get('/packages/omnitrade/stripe');
        self::assertStringContainsString('composer require omnitrade/stripe:1.x-dev', $html);
        self::assertStringContainsString('Verified against the real service on 2026-10-01.', $html);
        self::assertStringContainsString('href="https://github.com/glitchr-studio/omnitrade-stripe"', $html);
        self::assertStringContainsString('href="/packages/omniscient/omnitrade"', $html, 'what it requires');

        $own = $this->get('/packages/omniscient/omnitrade');
        self::assertStringContainsString('href="/docs/omnitrade/1.x"', $own, 'its manual, at its default series');
        self::assertStringContainsString('href="/docs/omnitrade/1.x/installation"', $own);
        self::assertStringContainsString('href="/docs/omnitrade/0.9"', $own);

        $this->get('/packages/omnitrade/nothing', 404);
    }

    public function testAFamilyPageListsItsProviders(): void
    {
        $html = $this->get('/families/omnitrade');
        self::assertStringContainsString('Omni<b>trade</b>', $html);
        self::assertStringContainsString('One contract for payment providers.', $html);
        self::assertStringContainsString('href="/packages/omnitrade/paypal"', $html);
        // The bridges have no family page.
        $this->get('/families/omnibridge', 404);
    }

    public function testTheGuidesAreTheSitesMarkdownInEachLanguage(): void
    {
        $html = $this->get('/guides/getting-started');
        self::assertStringContainsString('<h1>Getting started</h1>', $html);
        self::assertStringContainsString('href="/guides/compatibility"', $html, 'a link to another guide goes to its page');
        self::assertStringContainsString('class="doc-callout doc-callout-note"', $html);
        self::assertStringContainsString('<link rel="alternate" hreflang="fr" href="http://localhost:8135/fr/guides/getting-started">', $html);

        $fr = $this->get('/fr/guides/getting-started');
        self::assertStringContainsString('<h1>Démarrer</h1>', $fr);
        self::assertStringContainsString('href="/fr/guides/compatibility"', $fr);

        $docs = $this->get('/docs');
        self::assertStringContainsString('href="/guides/architecture"', $docs);
        self::assertStringContainsString('href="/docs/omnitrade/1.x"', $docs);
    }

    public function testTheNewsAndTheirFeeds(): void
    {
        $html = $this->get('/news');
        self::assertStringContainsString('href="/news/first"', $html);
        self::assertStringContainsString('href="/news/english-only"', $html);

        $item = $this->get('/news/first');
        self::assertStringContainsString('A <strong>first</strong> piece of news.', $item);
        self::assertStringContainsString('hreflang="fr" href="http://localhost:8135/fr/actualites/first"', $item);

        // Written in English only: no French alternate, and no French page.
        $only = $this->get('/news/english-only');
        self::assertStringContainsString('<h1>Only in English</h1>', $only);
        self::assertStringNotContainsString('hreflang="fr"', $only);
        $this->get('/fr/actualites/english-only', 404);

        $atom = $this->get('/news/feed.atom');
        self::assertStringStartsWith('application/atom+xml', (string) $this->client->getResponse()->headers->get('Content-Type'));
        $feed = simplexml_load_string($atom);
        self::assertNotFalse($feed);
        self::assertCount(2, $feed->entry);
        self::assertSame('http://localhost:8135/news/english-only', (string) $feed->entry[0]->id, 'latest first, at SITE_URL');

        $rss = simplexml_load_string($this->get('/fr/actualites/feed.rss'));
        self::assertNotFalse($rss);
        self::assertSame('La première actualité', (string) $rss->channel->item[0]->title);
    }

    public function testTheSearchIndexHoldsEveryKindOfPage(): void
    {
        $this->get('/search');
        $index = json_decode($this->get('/search.json'), true, flags: \JSON_THROW_ON_ERROR);
        self::assertSame('en', $index['locale']);
        $kinds = array_count_values(array_column($index['entries'], 'k'));
        self::assertSame(1, $kinds['family']);
        self::assertSame(2, $kinds['provider']);
        self::assertSame(2, $kinds['news']);
        self::assertSame(3, $kinds['doc'], 'the default series\' home and two pages');
        self::assertGreaterThanOrEqual(5, $kinds['guide']);
        $urls = array_column($index['entries'], 'u');
        self::assertContains('/docs/omnitrade/1.x/guides/webhooks', $urls);
        self::assertNotContains('/docs/omnitrade/0.9', $urls, 'only the default series is searched');

        $fr = json_decode($this->get('/fr/search.json'), true, flags: \JSON_THROW_ON_ERROR);
        self::assertContains('/fr/paquets/omnitrade/stripe', array_column($fr['entries'], 'u'));
        self::assertSame(1, array_count_values(array_column($fr['entries'], 'k'))['news']);
    }

    public function testRobotsAndTheUnknownAddressesPage(): void
    {
        self::assertSame("User-agent: *\nAllow: /\n\nSitemap: http://localhost:8135/sitemap.xml\n", $this->get('/robots.txt'));
        $html = $this->get('/not-found', 404);
        self::assertStringNotContainsString('rel="canonical"', $html);
        self::assertStringNotContainsString('hreflang=', $html);
    }

    public function testNoPageOpensTheDatabase(): void
    {
        foreach (['/', '/fr', '/packages', '/families/omnitrade', '/packages/omnitrade/stripe', '/docs', '/docs/omnitrade/1.x/installation', '/guides/architecture', '/news/first', '/news/feed.atom', '/search.json'] as $path) {
            $this->get($path);
        }
        self::assertFalse(static::getContainer()->get('doctrine.dbal.default_connection')->isConnected(), 'the pages are rendered from files: no database at build time');
    }
}
