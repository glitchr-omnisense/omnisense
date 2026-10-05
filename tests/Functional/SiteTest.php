<?php

namespace App\Tests\Functional;

use App\Tests\Mock\NoNetwork;
use Symfony\Component\BrowserKit\Cookie;

/**
 * The public site: every page answers; the home page turns the suffixes of
 * the families that exist, and only those; it speaks French then English;
 * the Demos page says which sites are fictional and leaves Taratata out;
 * the Support page, without a token, says there is no data and asks GitHub
 * nothing; the footer signs with Glitch Art's signature.
 */
class SiteTest extends SiteTestCase
{
    public function testEveryPublicPageAnswersWithoutCallingAnyone(): void
    {
        foreach (['/', '/packages', '/families/omnitrade', '/families/omnibase', '/packages/glitchr/omnitrade', '/packages/glitchr/stickyjs', '/docs', '/docs/glitchr/omnitrade/current/installation', '/docs/glitchr/omnibus/2.x', '/docs/_search?q=webhook', '/demos', '/support', '/blog', '/blog/les-manuels-se-lisent-dans-les-depots', '/blog/feed.xml', '/legal', '/privacy', '/login'] as $path) {
            $this->client->request('GET', $path);
            self::assertResponseIsSuccessful($path);
        }
        self::assertSame([], NoNetwork::$requests, 'a page asks nothing of Packagist, npm or GitHub: the command does');
    }

    /** On its own client: omnibase's sign-in and sign-up forms do not survive each other in one session. */
    public function testAnyoneMaySignUp(): void
    {
        $this->client->request('GET', '/register');
        self::assertResponseIsSuccessful();
    }

    public function testTheHomePageTurnsTheSuffixesOfTheFamiliesThatExist(): void
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
        // Nothing announced turns: omnisign, omnidns... are names, not families.
        self::assertStringNotContainsString('Omnisign', $page->filter('#content')->text());
        self::assertStringNotContainsString('dns', $page->filter('.om-omni-index')->text());

        // The floors, counted in the catalogue; each section is a stop.
        self::assertSame(['2', '1', '4', '3'], $page->filter('.om-floor-level')->each(fn ($n) => $n->text()));
        self::assertStringContainsString('1 paquet', $page->filter('.om-floor.is-foundation .om-floor-count')->text());
        self::assertStringContainsString('2 paquets', $page->filter('.om-floor.is-family .om-floor-count')->text());
        self::assertStringContainsString('3 paquets', $page->filter('.om-floor.is-provider .om-floor-count')->text());
        self::assertCount(4, $page->filter('#content > .sticky-stop'));
        self::assertSame(['Omnibase', 'Omnibus', 'Omnitrade'], $page->filter('.om-family-name')->each(fn ($n) => $n->text()));
        self::assertStringContainsString('2 fournisseurs', $page->filter('.om-family-list li')->eq(2)->text());

        // The latest announcements, and Glitch Art's signature (omnibase's partial, black).
        self::assertCount(3, $page->filter('.om-news li'));
        self::assertCount(1, $page->filter('.om-footer-credits .ga-credits.ga-credits--black'));
    }

    public function testTheSiteSpeaksEnglishToo(): void
    {
        $this->client->getCookieJar()->set(new Cookie('LANG', 'en'));
        $page = $this->client->request('GET', '/');

        self::assertSame('en', $page->filter('html')->attr('lang'));
        self::assertSame('The foundation of the sites, and one component per trade.', $page->filter('.om-omni-phrase')->text());
        self::assertStringContainsString('Four floors, two buildings', $page->filter('#floors h2')->text());
        self::assertStringContainsString('Packages', $page->filter('.om-nav')->text());

        $page = $this->client->request('GET', '/support');
        self::assertStringContainsString('No data.', $page->filter('.om-nodata')->text());
    }

    public function testNoPageSpeaksInTheFirstPersonNorNamesAnAssistant(): void
    {
        foreach (['/', '/packages', '/demos', '/support', '/legal', '/privacy', '/blog/les-familles-tournent-sans-cadre'] as $path) {
            $text = $this->client->request('GET', $path)->filter('#content')->text();
            self::assertDoesNotMatchRegularExpression('/\b(je|j’ai|j\'ai|mes|mon site|chez moi)\b/iu', $text, $path);
        }
    }

    public function testTheDemosSayWhichSitesAreFictional(): void
    {
        $page = $this->client->request('GET', '/demos');

        self::assertStringNotContainsStringIgnoringCase('taratata', $page->filter('#content')->html(), 'a private demonstration is not listed');
        $demo = $page->filter('#demo');
        self::assertSame(['Maison de santé du Ried', 'Étude Aldebert & Fontvielle', 'Cabinet Maréval', 'Côme Aubertel'], $demo->filter('h3 a')->each(fn ($a) => $a->text()));
        self::assertCount(4, $demo->filter('.om-fictional'));
        self::assertStringContainsString('n’existe pas', $demo->filter('li')->last()->text());
        // The real sites carry no such mark, and each links to its own address.
        self::assertCount(0, $page->filter('#client .om-fictional'));
        self::assertSame('https://localhost:8443', $page->filter('#client h3 a')->reduce(fn ($a) => 'Nakaya Gourmet' === $a->text())->attr('href'));
        // "Used by", read in the fixtures' composer.json: Nakaya requires the foundation, omnitrade and its Stripe provider.
        $nakaya = $page->filter('#client li')->reduce(fn ($li) => str_contains($li->text(), 'Nakaya Gourmet'));
        self::assertSame(['glitchr/omnibase', 'glitchr/omnitrade', 'omnitrade/stripe'], $nakaya->filter('.om-names a')->each(fn ($a) => $a->text()));
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
