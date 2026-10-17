<?php

namespace App\Tests\Functional;

use App\Tests\Mock\NoNetwork;
use Symfony\Component\BrowserKit\Cookie;

/**
 * The public site: every page answers; the home page turns the suffixes of
 * the families that exist, and only those, and leads to the documentation;
 * it shows the foundation - the core, the families, the bridges - and apart,
 * optional, the prepared structures, and names no provider;
 * nothing counts or names the sites built on the packages, and the old
 * Demos address sends to the documentation; it speaks French then English;
 * the Support page, without a token, says there is no data and asks GitHub
 * nothing; the footer signs with Glitch Art's signature.
 */
class SiteTest extends SiteTestCase
{
    public function testEveryPublicPageAnswersWithoutCallingAnyone(): void
    {
        foreach (['/', '/packages', '/packages?q=stripe&family=omnitrade&kind=provider', '/packages.json?q=stripe', '/families/omnitrade', '/families/omnibase', '/packages/glitchr/omnitrade', '/packages/glitchr/flowjs', '/docs', '/docs/glitchr/omnitrade/current/installation', '/docs/glitchr/omnibus/2.x', '/docs/_search?q=webhook', '/install', '/support', '/blog', '/blog/les-manuels-se-lisent-dans-les-depots', '/blog/feed.xml', '/legal', '/privacy', '/login'] as $path) {
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

    /** With `ends` (flowjs 1.4) an arrow rests at a tall section's bottom before the next section's top: its end was skipped. */
    public function testTheArrowKeysShowATallSectionsBottom(): void
    {
        $source = file_get_contents(\dirname(__DIR__, 2).'/assets/app-defer.js');

        self::assertMatchesRegularExpression('/stops:\s*\{[^}]*ends:\s*true/', $source);
    }

    /**
     * The floors' examples turn without moving the page: a row's chips keep one width (the longest name, or
     * their share of the row beside its "+ N"), a longer name cut rather than pushing the "+ N" down a line.
     */
    public function testTheTurningExamplesKeepTheirPlace(): void
    {
        $controller = file_get_contents(\dirname(__DIR__, 2).'/assets/controllers/examples_controller.js');
        $styles = file_get_contents(\dirname(__DIR__, 2).'/assets/styles/layout1.scss');

        self::assertStringContainsString('this.reserve();', $controller);
        self::assertStringContainsString('document.fonts?.ready.then(() => this.reserve())', $controller, 'measured again once the fonts are in');
        self::assertStringContainsString('new ResizeObserver(() => this.reserve())', $controller, 'and when the row\'s width changes');
        self::assertStringContainsString('chip.style.width = `${width}px`', $controller);
        self::assertMatchesRegularExpression('/\.om-floor-examples \{[^}]*code \{[^}]*white-space: nowrap;[^}]*overflow: hidden; text-overflow: ellipsis;/', $styles);

        $this->client->request('GET', '/');
        self::assertResponseIsSuccessful();
        $page = $this->client->getCrawler();
        self::assertGreaterThan(0, $page->filter('[data-controller~="examples"] [data-examples-target="family"]')->count());
        self::assertGreaterThan(0, $page->filter('[data-controller~="examples"] [data-examples-target="component"]')->count());
        self::assertCount(0, $page->filter('[data-examples-target="provider"]'), 'no provider turns on the home page');
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
        self::assertSame(['base', 'bus', 'trade'], $page->filter('.om-omni-tags [data-suffix-target="item"]')->each(fn ($a) => $a->attr('data-suffix')));
        self::assertSame('base', $page->filter('.om-omni-slot')->text());
        self::assertSame('Le cœur des sites, et des structures toutes prêtes, facultatives.', $page->filter('.om-omni-phrase')->text());
        self::assertSame('Encaisser et vendre, quel que soit le prestataire.', $page->filter('[data-suffix="trade"]')->attr('data-phrase'));
        self::assertCount(1, $page->filter('[data-controller="suffix"]'));
        // Nothing announced turns: omnishow, omnidns... are names, not families.
        self::assertStringNotContainsString('Omnishow', $page->filter('#omni')->text());
        self::assertStringNotContainsString('dns', $page->filter('.om-omni-tags')->text());

        // The search through every manual comes first, beside the mark.
        self::assertSame('/docs/_search', $page->filter('.om-hero form[role="search"]')->attr('action'));
        self::assertCount(1, $page->filter('#content form[role="search"]'));

        // The way on is the documentation: the suffix, each family's tag, the first button.
        self::assertSame('/docs', $page->filter('.om-omni-slot')->attr('href'));
        self::assertSame('/docs/glitchr/omnitrade', $page->filter('[data-suffix="trade"]')->attr('href'));
        self::assertSame('/docs', $page->filter('.om-hero .om-actions a')->first()->attr('href'));
        self::assertSame('/install', $page->filter('.om-hero .om-actions a')->eq(1)->attr('href'));
        self::assertSame(['/docs', '/install', '/blog', '/support'], $page->filter('.om-nav a')->each(fn ($a) => $a->attr('href')), 'the catalogue is reached from the documentation, not from the menu');

        // The foundation: the core, the families, the bridges, counted in the catalogue; each section is a stop.
        self::assertSame(['1', '2', '3'], $page->filter('#floors .om-floor-level')->each(fn ($n) => $n->text()));
        self::assertSame(['Cœur', 'Familles', 'Ponts'], $page->filter('#floors .om-floor-name')->each(fn ($n) => $n->text()));
        // The families' own packages turn; "omni" in grey, the domain in blue.
        self::assertSame(['glitchr/omnibus', 'glitchr/omnitrade'], $page->filter('.om-floor.is-family .om-floor-examples code')->each(fn ($c) => $c->text()));
        self::assertSame(['glitchr/omnibus', 'glitchr/omnitrade'], json_decode($page->filter('#floors [data-controller="examples"]')->attr('data-examples-families-value'), true));
        self::assertSame('omni', $page->filter('.om-floor.is-family .om-floor-pattern .om-mark-omni')->text());
        self::assertStringContainsString('1 paquet', $page->filter('.om-floor.is-foundation .om-floor-count')->text());
        self::assertStringContainsString('2 paquets · 3 fournisseurs à chercher', $page->filter('.om-floor.is-family .om-floor-count')->text());
        self::assertSame('/packages?kind=family', $page->filter('.om-floor.is-family')->attr('href'));
        self::assertCount(7, $page->filter('#content > .sticky-stop'));

        // No provider is named on the home page: they are counted, and the count leads to the search.
        $text = $page->filter('#content')->text();
        foreach (['omnitrade/stripe', 'omnitrade/paypal', 'omnibus/offline'] as $provider) {
            self::assertStringNotContainsString($provider, $text);
        }
        self::assertSame(['Omnibus', 'Omnitrade'], $page->filter('#families .om-family-name')->each(fn ($n) => $n->text()), 'omnibase is the core, not a family');
        self::assertSame('2 fournisseurs', $page->filter('#families .om-family-count')->eq(1)->text());
        self::assertSame('/packages?family=omnitrade&kind=provider', $page->filter('#families .om-family-count')->eq(1)->attr('href'));
        self::assertSame('/families/omnitrade', $page->filter('#families a.om-family-name')->eq(1)->attr('href'));
        self::assertStringNotContainsString('question', $page->filter('#families h2')->text());
        // The names that are only announced, apart and said to be so.
        self::assertStringContainsString('Omnidns', $page->filter('#announced')->text());
        self::assertStringContainsString('nom réservé', $page->filter('#announced')->text());
        self::assertCount(0, $page->filter('#announced a'), 'nothing to open: there is no package');

        // omnibridge has its own section: the targets, none in the fixtures' repositories.
        self::assertSame(['omnibridge/symfony', 'omnibridge/doctrine', 'omnibridge/twig', 'omnibridge/react', 'omnibridge/composer', 'omnibridge/laravel'], $page->filter('#bridges .om-family-name')->each(fn ($n) => $n->text()));
        self::assertSame(['Prévu'], array_values(array_unique($page->filter('#bridges .om-family-count')->each(fn ($n) => $n->text()))));

        // The prepared structures: a section of their own, said to be optional, never beside the families.
        $structures = $page->filter('#structures');
        self::assertStringContainsString('Facultatif', $structures->filter('.om-optional')->text());
        self::assertStringContainsString('Facultative : la prendre pour aller vite', $structures->text());
        self::assertSame(['omnibase/blog'], $structures->filter('[data-examples-target="component"]')->each(fn ($c) => $c->text()));
        self::assertSame(['omnibase/blog'], json_decode($structures->filter('[data-controller="examples"]')->attr('data-examples-structures-value'), true));
        self::assertStringNotContainsString('omnitrade', $structures->text());
        self::assertStringNotContainsString('omnibase/', $page->filter('#floors')->text().$page->filter('#families')->text());

        // Which frameworks: what is true today, what is planned - and no date.
        $compat = $page->filter('#compatibility');
        self::assertStringContainsString('Compatible avec n’importe quel cadre', $compat->filter('h2')->text());
        self::assertSame(['Aujourd’hui', 'Aujourd’hui', 'Prévu'], $compat->filter('h3 .om-fact')->each(fn ($f) => $f->text()));
        self::assertStringContainsString('Tests/BareTest.php', $compat->text());
        self::assertStringContainsString('omnibase deviendra agnostique', $compat->text());
        self::assertCount(0, $compat->filter('.om-compat-bridges'), 'the bridges are listed once, in their own section');
        self::assertSame('#bridges', $compat->filter('a[href="#bridges"]')->attr('href'));
        self::assertDoesNotMatchRegularExpression('/\b20\d\d\b/', $compat->text(), 'no date promised');

        // The latest announcements, and Glitch Art's signature (omnibase's partial, black).
        self::assertCount(3, $page->filter('.om-news li'));
        self::assertCount(1, $page->filter('.om-footer-credits .ga-credits.ga-credits--black'));
    }

    public function testNoPublicPageCountsOrNamesTheSitesBuiltOnThePackages(): void
    {
        foreach (['/', '/packages', '/packages?q=stripe&family=omnitrade&kind=provider', '/families/omnitrade', '/packages/glitchr/omnitrade', '/packages/glitchr/flowjs', '/docs', '/install'] as $path) {
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
        self::assertSame('The core of the sites, and optional ready-made structures.', $page->filter('.om-omni-phrase')->text());
        self::assertStringContainsString('The core, the families, the bridges', $page->filter('#floors h2')->text());
        self::assertStringContainsString('Optional: take one to go fast', $page->filter('#structures')->text());
        self::assertStringContainsString('One bridge per framework', $page->filter('#bridges h2')->text());
        self::assertSame(['Documentation', 'Install', 'News', 'Support'], $page->filter('.om-nav a')->each(fn ($a) => $a->text()));
        self::assertStringContainsString('Compatible with any framework', $page->filter('#compatibility h2')->text());

        $page = $this->client->request('GET', '/support');
        self::assertStringContainsString('No data.', $page->filter('.om-nodata')->text());
    }

    public function testNoPageSpeaksInTheFirstPersonNorNamesAnAssistant(): void
    {
        foreach (['/', '/packages', '/install', '/install?need=structure', '/support', '/legal', '/privacy', '/blog/les-familles-tournent-sans-cadre'] as $path) {
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
