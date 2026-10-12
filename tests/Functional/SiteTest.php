<?php

namespace App\Tests\Functional;

use App\Tests\Mock\NoNetwork;
use Symfony\Component\BrowserKit\Cookie;

/**
 * The public site: every page answers; the home page turns the suffixes of
 * the families that exist, and only those, and leads to the documentation;
 * nothing counts or names the sites built on the packages, and the old
 * Demos address sends to the documentation; it speaks French then English;
 * the Support page, without a token, says there is no data and asks GitHub
 * nothing; the footer signs with Glitch Art's signature.
 */
class SiteTest extends SiteTestCase
{
    public function testEveryPublicPageAnswersWithoutCallingAnyone(): void
    {
        foreach (['/', '/packages', '/families/omnitrade', '/families/omnibase', '/packages/glitchr/omnitrade', '/packages/glitchr/flowjs', '/docs', '/docs/glitchr/omnitrade/current/installation', '/docs/glitchr/omnibus/2.x', '/docs/_search?q=webhook', '/install', '/support', '/blog', '/blog/les-manuels-se-lisent-dans-les-depots', '/blog/feed.xml', '/legal', '/privacy', '/login'] as $path) {
            $this->client->request('GET', $path);
            self::assertResponseIsSuccessful($path);
        }
        self::assertSame([], NoNetwork::$requests, 'a page asks nothing of Packagist, npm or GitHub: the command does');
    }

    /** Without `keys` the arrow is the browser's 40px nudge, which creeps through a section taller than the screen. */
    public function testTheArrowKeysGoFromSectionToSection(): void
    {
        $source = file_get_contents(\dirname(__DIR__, 2).'/assets/app-defer.js');

        self::assertMatchesRegularExpression('/stops:\s*\{[^}]*keys:\s*true/', $source);
    }

    /** On its own client: omnibase's sign-in and sign-up forms do not survive each other in one session. */
    public function testAnyoneMaySignUp(): void
    {
        $this->client->request('GET', '/register');
        self::assertResponseIsSuccessful();
    }

    public function testTheHomePageTurnsTheSuffixesOfTheFamiliesThatExistAndLeadsToTheDocumentation(): void
    {
        $page = $this->client->request('GET', '/');

        self::assertSame('fr', $page->filter('html')->attr('lang'));
        self::assertSame('Omni', $page->filter('.om-omni-fixed')->text());
        // The fixtures' repositories hold omnibase, omnibus and omnitrade: three suffixes, in the home page's order.
        self::assertSame(['base', 'bus', 'trade'], $page->filter('.om-omni-index [data-suffix-target="item"]')->each(fn ($a) => $a->attr('data-suffix')));
        self::assertSame('base', $page->filter('.om-omni-slot')->text());
        self::assertSame('Le socle des sites, et un composant par métier.', $page->filter('.om-omni-phrase')->text());
        self::assertSame('Encaisser et vendre, quel que soit le prestataire.', $page->filter('[data-suffix="trade"]')->attr('data-phrase'));
        self::assertCount(1, $page->filter('[data-controller="suffix"]'));
        // Nothing announced turns: omnishow, omnidns... are names, not families.
        self::assertStringNotContainsString('Omnishow', $page->filter('#content')->text());
        self::assertStringNotContainsString('dns', $page->filter('.om-omni-index')->text());

        // The way on is the documentation: the suffix, each family of the index, the first button.
        self::assertSame('/docs', $page->filter('.om-omni-slot')->attr('href'));
        self::assertSame('/docs/glitchr/omnitrade', $page->filter('[data-suffix="trade"]')->attr('href'));
        self::assertSame('/docs', $page->filter('.om-hero .om-actions a')->first()->attr('href'));
        self::assertSame('/install', $page->filter('.om-hero .om-actions a')->eq(1)->attr('href'));
        self::assertSame(['/docs', '/install', '/blog', '/support'], $page->filter('.om-nav a')->each(fn ($a) => $a->attr('href')), 'the catalogue is reached from the documentation, not from the menu');

        // The floors, counted in the catalogue; each section is a stop.
        self::assertSame(['2', '1', '4', '3'], $page->filter('.om-floor-level')->each(fn ($n) => $n->text()));
        self::assertStringContainsString('1 paquet', $page->filter('.om-floor.is-foundation .om-floor-count')->text());
        self::assertStringContainsString('2 paquets', $page->filter('.om-floor.is-family .om-floor-count')->text());
        self::assertStringContainsString('3 paquets', $page->filter('.om-floor.is-provider .om-floor-count')->text());
        self::assertCount(5, $page->filter('#content > .sticky-stop'));
        self::assertSame(['Omnibase', 'Omnibus', 'Omnitrade'], $page->filter('.om-family-name')->each(fn ($n) => $n->text()));
        self::assertStringContainsString('2 fournisseurs', $page->filter('.om-family-list li')->eq(2)->text());

        // Which frameworks: what is true today, what is planned - and no date.
        $compat = $page->filter('#compatibility');
        self::assertStringContainsString('Compatible avec n’importe quel cadre', $compat->filter('h2')->text());
        self::assertSame(['Aujourd’hui', 'Aujourd’hui', 'Prévu'], $compat->filter('h3 .om-fact')->each(fn ($f) => $f->text()));
        self::assertStringContainsString('Tests/BareTest.php', $compat->text());
        self::assertStringContainsString('omnibase deviendra agnostique', $compat->text());
        self::assertSame(['omnibridge/symfony', 'omnibridge/doctrine', 'omnibridge/twig', 'omnibridge/composer', 'omnibridge/laravel'], $compat->filter('.om-compat-bridges code')->each(fn ($c) => $c->text()));
        self::assertDoesNotMatchRegularExpression('/\b20\d\d\b/', $compat->text(), 'no date promised');

        // The latest announcements, and Glitch Art's signature (omnibase's partial, black).
        self::assertCount(3, $page->filter('.om-news li'));
        self::assertCount(1, $page->filter('.om-footer-credits .ga-credits.ga-credits--black'));
    }

    public function testNoPublicPageCountsOrNamesTheSitesBuiltOnThePackages(): void
    {
        foreach (['/', '/packages', '/families/omnitrade', '/packages/glitchr/omnitrade', '/packages/glitchr/flowjs', '/docs', '/install'] as $path) {
            $text = $this->client->request('GET', $path)->filter('#content')->text();
            self::assertDoesNotMatchRegularExpression('/sites? (construits?|qui s’en servent)|utilisé par|Nakaya|Côme Aubertel|Démos/u', $text, $path);
            self::assertCount(0, $this->client->getCrawler()->filter('.om-sites, .om-demos, a[href="/demos"]'), $path);
        }

        // The old address of the Demos page sends to the documentation, for good.
        $this->client->request('GET', '/demos');
        self::assertResponseRedirects('/docs', 301);
    }

    public function testTheSiteSpeaksEnglishToo(): void
    {
        $this->client->getCookieJar()->set(new Cookie('LANG', 'en'));
        $page = $this->client->request('GET', '/');

        self::assertSame('en', $page->filter('html')->attr('lang'));
        self::assertSame('The foundation of the sites, and one component per trade.', $page->filter('.om-omni-phrase')->text());
        self::assertStringContainsString('Four floors, two buildings', $page->filter('#floors h2')->text());
        self::assertSame(['Documentation', 'Install', 'News', 'Support'], $page->filter('.om-nav a')->each(fn ($a) => $a->text()));
        self::assertStringContainsString('Compatible with any framework', $page->filter('#compatibility h2')->text());

        $page = $this->client->request('GET', '/support');
        self::assertStringContainsString('No data.', $page->filter('.om-nodata')->text());
    }

    public function testNoPageSpeaksInTheFirstPersonNorNamesAnAssistant(): void
    {
        foreach (['/', '/packages', '/install', '/support', '/legal', '/privacy', '/blog/les-familles-tournent-sans-cadre'] as $path) {
            $text = $this->client->request('GET', $path)->filter('#content')->text();
            self::assertDoesNotMatchRegularExpression('/\b(je|j’ai|j\'ai|mes|mon site|chez moi)\b/iu', $text, $path);
        }
    }

    public function testTheSupportPageWithoutATokenSaysThereIsNoData(): void
    {
        $page = $this->client->request('GET', '/support');

        self::assertResponseIsSuccessful();
        self::assertSame('unconfigured', $page->filter('.om-support')->attr('data-sponsors'));
        self::assertStringContainsString('Pas de données.', $page->filter('.om-nodata')->text());
        self::assertStringContainsString('n’a pas de jeton GitHub', $page->filter('.om-nodata')->text());
        self::assertStringContainsString('ni exemple, ni chiffre supposé', $page->filter('.om-nodata')->text());
        // Nothing stands in for an answer: no tier, no wall, no goal, no link to a Sponsors page that may not exist.
        self::assertCount(0, $page->filter('.om-tiers, .om-wall, .om-goal'));
        self::assertCount(0, $page->filter('a[href*="github.com/sponsors"]'));
        self::assertSame([], NoNetwork::$requests, 'GitHub was not asked');
        // The other ways to help are there.
        self::assertCount(3, $page->filter('.om-ways li'));
    }
}
