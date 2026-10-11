<?php

namespace App\Tests\Functional;

use App\Tests\Mock\FakeTypesense;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Component\Console\Tester\CommandTester;

/**
 * The manuals (omnibase/docs, on the site): a page with its contents, its
 * breadcrumb, previous and next, its own headings, its callouts and code,
 * its links followed, "Edit this page"; the versions of a manual and the
 * move from one to the other; the search - Typesense (its double) when it
 * answers, the pages' own index when it is stopped, and the page saying
 * which of the two answered.
 */
class DocsTest extends SiteTestCase
{
    /** The site's own pages are a manual too, at the version of the branch checked out (main; "current" without a .git). */
    private static function siteVersion(): string
    {
        return str_replace('.', '-', (string) (\Base\Wikidoc\Manual\Git::branch(\dirname(__DIR__, 2)) ?? 'current'));
    }

    public function testEveryManualGroupedAsTheCatalogue(): void
    {
        $page = $this->client->request('GET', '/docs');

        self::assertResponseIsSuccessful();
        // omnibus/offline has nothing to read: seven manuals for eight packages.
        self::assertSame(['glitchr/omnibase', 'omnibase/blog'], $page->filter('#omnibase .om-row-name')->each(fn ($a) => $a->text()));
        self::assertSame(['glitchr/omnibus'], $page->filter('#omnibus .om-row-name')->each(fn ($a) => $a->text()));
        self::assertSame(['glitchr/omnitrade', 'omnitrade/paypal', 'omnitrade/stripe'], $page->filter('#omnitrade .om-row-name')->each(fn ($a) => $a->text()));
        self::assertSame(['@glitchr/stickyjs'], $page->filter('#library .om-row-name')->each(fn ($a) => $a->text()));
        // A manual's versions, newest first; one made of a README alone says so.
        self::assertSame(['2.x', '1.x'], $page->filter('#omnibus .om-manual-versions a')->each(fn ($a) => $a->text()));
        self::assertStringContainsString('README seul', $page->filter('#omnitrade li')->eq(1)->text());
        self::assertCount(1, $page->filter('form[data-manual-search]'));
    }

    public function testTheEntryPageOrientsThenListsTheReference(): void
    {
        $page = $this->client->request('GET', '/docs');

        // The way in: the site's own pages, in their reading order, then the selector.
        self::assertSame(['Démarrer', 'Compatibilité', 'Conventions', 'Contribuer', 'Composer son installation'], $page->filter('.om-docs-ways .om-docs-way-title')->each(fn ($t) => $t->text()));
        self::assertSame('/docs/glitchr/omniscient/'.str_replace('-', '.', self::siteVersion()).'/demarrer', $page->filter('.om-docs-ways a')->first()->attr('href'));
        self::assertSame('/install', $page->filter('.om-docs-ways a')->last()->attr('href'));
        // The reference, grouped as the catalogue; the site's pages are not a package.
        self::assertSame(['omnibase', 'omnibus', 'omnitrade', 'library'], $page->filter('.om-docs-group')->each(fn ($g) => $g->attr('id')));
        self::assertStringNotContainsString('glitchr/omniscient', $page->filter('#reference')->text());
        self::assertStringContainsString('Proposer une modification', $page->filter('#contribute')->text());

        // The site's own page has no pencil: its repository is not public. A package's page has one, at the branch read.
        $page = $this->client->request('GET', '/docs/glitchr/omniscient/'.str_replace('-', '.', self::siteVersion()).'/contribuer');
        self::assertResponseIsSuccessful();
        self::assertCount(0, $page->filter('.manual-pencil'));
        self::assertStringContainsString('ne portent pas de crayon', $page->filter('.manual-prose')->text());
        $page = $this->client->request('GET', '/docs/glitchr/omnibus/1.x/installation');
        self::assertSame('https://github.com/glitchr-studio/omnibus/edit/1.x/docs/installation.md', $page->filter('.manual-pencil')->attr('href'));
        self::assertSame('Proposer une modification', trim($page->filter('.manual-pencil')->text()));
    }

    public function testAPageOfAManual(): void
    {
        $page = $this->client->request('GET', '/docs/glitchr/omnitrade/current/installation');
        self::assertResponseIsSuccessful();

        // The breadcrumb: Documentation / the manual / the version / the page.
        self::assertSame(['Documentation', 'glitchr/omnitrade', 'current', 'Installation'], $page->filter('.manual-crumbs li')->each(fn ($li) => trim($li->text())));
        // The manual's contents, the current page marked.
        self::assertSame(['Présentation', 'Installation', 'Webhooks'], $page->filter('.manual-tree a')->each(fn ($a) => trim($a->text())));
        self::assertSame('Installation', trim($page->filter('.manual-tree a[aria-current="page"]')->text()));
        // The page: its own title, its headings anchored and listed.
        self::assertSame('Installation and first calls', $page->filter('.manual-prose h1')->text());
        self::assertSame(['#plain-php', '#in-a-framework'], $page->filter('.manual-toc a')->each(fn ($a) => $a->attr('href')));
        self::assertCount(1, $page->filter('.manual-prose #plain-php'));
        // Callouts, in the reader's language; code framed and named.
        self::assertSame('Note', $page->filter('blockquote.doc-callout-note')->attr('data-label'));
        self::assertSame('Attention', $page->filter('blockquote.doc-callout-warning')->attr('data-label'));
        self::assertSame(['sh', 'php'], $page->filter('.doc-code')->each(fn ($c) => $c->attr('data-lang')));
        self::assertStringContainsString('composer require glitchr/omnitrade omnitrade/stripe', $page->filter('.doc-code')->first()->text());
        self::assertGreaterThan(0, $page->filter('.doc-code [class^="hl-"]')->count(), 'coloured on the server');
        // Relative links: another page, the README, a folder of the repository on GitHub.
        $links = $page->filter('.manual-prose p a')->each(fn ($a) => $a->attr('href'));
        self::assertSame(['/docs/glitchr/omnitrade/current/webhooks', 'https://github.com/glitchr-studio/omnitrade/tree/current/src/Bridge/Symfony', '/docs/glitchr/omnitrade/current'], $links);
        // Edit this page, previous and next.
        self::assertSame('https://github.com/glitchr-studio/omnitrade/edit/current/docs/installation.md', $page->filter('.manual-edit > a')->attr('href'));
        self::assertSame('docs/installation.md', $page->filter('.manual-source code')->text());
        self::assertSame('/docs/glitchr/omnitrade/current', $page->filter('.manual-neighbours a[rel="prev"]')->attr('href'));
        self::assertSame('/docs/glitchr/omnitrade/current/webhooks', $page->filter('.manual-neighbours a[rel="next"]')->attr('href'));

        // The README is the manual's home; its links are relative to the repository's root.
        $page = $this->client->request('GET', '/docs/glitchr/omnitrade/current');
        self::assertSame('glitchr/omnitrade', $page->filter('.manual-prose h1')->text());
        self::assertContains('/docs/glitchr/omnitrade/current/webhooks#signature', $page->filter('.manual-prose a')->each(fn ($a) => $a->attr('href')));
        self::assertSame('https://github.com/glitchr-studio/omnitrade/edit/current/README.md', $page->filter('.manual-edit > a')->attr('href'));

        // What is not there is a 404, not a blank page.
        $this->client->request('GET', '/docs/glitchr/omnitrade/current/refunds');
        self::assertResponseStatusCodeSame(404);
        $this->client->request('GET', '/docs/glitchr/omnisign');
        self::assertResponseStatusCodeSame(404);
    }

    public function testFromOneVersionToAnother(): void
    {
        // Without a version, or with "current": the default one, spelled out.
        $this->client->request('GET', '/docs/glitchr/omnibus');
        self::assertResponseRedirects('/docs/glitchr/omnibus/2.x');
        $this->client->request('GET', '/docs/glitchr/omnibus/current/installation');
        self::assertResponseRedirects('/docs/glitchr/omnibus/2.x/installation');

        $page = $this->client->request('GET', '/docs/glitchr/omnibus/2.x/installation');
        self::assertStringContainsString('The 2.x way', $page->filter('.manual-prose')->text());
        self::assertSame(['Présentation', 'Installation', 'Pickup points'], $page->filter('.manual-tree a')->each(fn ($a) => trim($a->text())));
        self::assertCount(0, $page->filter('.manual-notice'), 'the default version carries no notice');
        // The version menu: the same page in each version.
        $versions = $page->filter('.manual-versions li a');
        self::assertSame(['2.x' => '/docs/glitchr/omnibus/2.x/installation', '1.x' => '/docs/glitchr/omnibus/1.x/installation'], array_combine($versions->each(fn ($a) => $a->text()), $versions->each(fn ($a) => $a->attr('href'))));
        self::assertSame('2.x', $page->filter('.manual-versions li a[aria-current]')->text());

        // 1.x: its own text, its own contents, its own branch to edit, and the way back to the current version.
        $page = $this->client->click($versions->eq(1)->link());
        self::assertResponseIsSuccessful();
        self::assertStringContainsString('The 1.x way', $page->filter('.manual-prose')->text());
        self::assertSame(['Présentation', 'Installation'], $page->filter('.manual-tree a')->each(fn ($a) => trim($a->text())));
        self::assertSame('https://github.com/glitchr-studio/omnibus/edit/1.x/docs/installation.md', $page->filter('.manual-edit > a')->attr('href'));
        self::assertStringContainsString('La version courante est la 2.x', $page->filter('.manual-notice')->text());
        self::assertSame('/docs/glitchr/omnibus/2.x/installation', $page->filter('.manual-notice a')->attr('href'));

        // A page that only 2.x has: from it, 1.x opens on its overview.
        $page = $this->client->request('GET', '/docs/glitchr/omnibus/2.x/pickup-points');
        self::assertSame('/docs/glitchr/omnibus/1.x', $page->filter('.manual-versions li a')->eq(1)->attr('href'));
        $this->client->request('GET', '/docs/glitchr/omnibus/1.x/pickup-points');
        self::assertResponseStatusCodeSame(404);
    }

    public function testTheSearchWithTypesenseThenWithout(): void
    {
        // wikidoc:index fills the engine: a collection per manual and version.
        $index = new CommandTester((new Application(static::$kernel))->find('wikidoc:index'));
        self::assertSame(0, $index->execute([]));
        $collections = array_keys(FakeTypesense::$collections);
        sort($collections);
        self::assertSame([
            'omniscient_glitchr-omnibase_current', 'omniscient_glitchr-omnibus_1-x', 'omniscient_glitchr-omnibus_2-x', 'omniscient_glitchr-omniscient_'.self::siteVersion(), 'omniscient_glitchr-omnitrade_current',
            'omniscient_glitchr-stickyjs_current', 'omniscient_omnibase-blog_current', 'omniscient_omnitrade-paypal_current', 'omniscient_omnitrade-stripe_current',
        ], $collections);
        self::assertSame(['Signature'], array_values(array_filter(array_column(FakeTypesense::$collections['omniscient_glitchr-omnitrade_current'], 'section'), fn ($s) => 'Signature' === $s)));

        // The engine answers - a typo forgiven, which the local index would not do.
        $page = $this->client->request('GET', '/docs/_search?q=webhok');
        self::assertResponseIsSuccessful();
        self::assertSame('typesense', $page->filter('.manual-results')->attr('data-engine'));
        self::assertStringContainsString('Réponse de Typesense', $page->filter('.manual-engine')->text());
        $hits = $page->filter('.manual-hits a')->each(fn ($a) => $a->attr('href'));
        self::assertSame('/docs/glitchr/omnitrade/current/webhooks', $hits[0], 'the page whose title it is comes first');
        self::assertContains('/docs/omnitrade/stripe/current', $hits, 'several packages answer');
        // Every manual, each in its default version: omnibus is searched in 2.x.
        self::assertContains('omniscient_glitchr-omnibus_2-x', FakeTypesense::$searches[0]);
        self::assertNotContains('omniscient_glitchr-omnibus_1-x', FakeTypesense::$searches[0]);

        // In one manual and one version; and as JSON, for the palette.
        $this->client->request('GET', '/docs/_search?q=installation&manual=glitchr/omnibus&version=1.x&format=json');
        $json = json_decode((string) $this->client->getResponse()->getContent(), true);
        self::assertSame('typesense', $json['engine']);
        self::assertSame(['omniscient_glitchr-omnibus_1-x'], end(FakeTypesense::$searches));
        self::assertSame(['glitchr/omnibus', '1.x', '/docs/glitchr/omnibus/1.x/installation'], [$json['hits'][0]['manual'], $json['hits'][0]['version'], $json['hits'][0]['url']]);

        // Typesense stopped: the pages' own index answers, and the page says so.
        FakeTypesense::$up = false;
        $page = $this->client->request('GET', '/docs/_search?q=webhook');
        self::assertResponseIsSuccessful();
        self::assertSame('local', $page->filter('.manual-results')->attr('data-engine'));
        self::assertStringContainsString('le moteur de recherche est éteint', $page->filter('.manual-engine')->text());
        $hits = $page->filter('.manual-hits a')->each(fn ($a) => $a->attr('href'));
        self::assertSame('/docs/glitchr/omnitrade/current/webhooks', $hits[0]);
        self::assertContains('/docs/omnitrade/stripe/current', $hits);
        self::assertGreaterThan(0, $page->filter('.manual-hits mark')->count(), 'the words searched are marked');
        // A section is a result of its own, linked to its heading.
        $page = $this->client->request('GET', '/docs/_search?q=signature');
        self::assertSame('/docs/glitchr/omnitrade/current/webhooks#signature', $page->filter('.manual-hits a')->first()->attr('href'));
        // No typo tolerance without the engine, and no error either.
        $page = $this->client->request('GET', '/docs/_search?q=webhok');
        self::assertResponseIsSuccessful();
        self::assertCount(0, $page->filter('.manual-hits a'));
        self::assertStringContainsString('Rien ne correspond', $page->filter('.manual-empty')->text());

        // The index of the manuals says which engine answers; the command says it could not index.
        self::assertSame('local', $this->client->request('GET', '/docs')->filter('[data-engine]')->attr('data-engine'));
        self::assertSame(1, $index->execute([]));
        self::assertStringContainsString('Typesense does not answer', $index->getDisplay());
    }

    public function testTheEngineUpButNeverFilledTheLocalIndexAnswers(): void
    {
        // The fake starts empty: no collection for any manual.
        $page = $this->client->request('GET', '/docs/_search?q=webhook');

        self::assertSame('local', $page->filter('.manual-results')->attr('data-engine'));
        self::assertGreaterThan(0, $page->filter('.manual-hits a')->count());
    }
}
