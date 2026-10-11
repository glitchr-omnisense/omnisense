<?php

namespace App\Tests\Functional;

use App\Tests\Mock\FakeTypesense;

/**
 * The back office: it opens in the nest (X-Transparent-Nest) for those who
 * run the site, every screen of its menu opens, the packages are read and
 * not typed, the manuals' screen copies branches and fills the index; a
 * member gets none of it.
 */
class AdminTest extends SiteTestCase
{
    public function testTheBackOfficeOpensInTheNestAndEveryScreenOfItsMenuOpens(): void
    {
        $this->signIn('glitchr');
        $dashboard = $this->client->request('GET', '/admin');

        self::assertResponseIsSuccessful();
        self::assertResponseHeaderSame('X-Transparent-Nest', 'overlay');
        // The menu of plan 0, for this site: Contenus, Métier, Comptes, Réglages.
        $menu = $dashboard->filter('body')->text();
        foreach (['Contenus', 'Articles', 'Métier', 'Paquets', 'Manuels', 'Suivis', 'Comptes', 'Réglages'] as $label) {
            self::assertStringContainsString($label, $menu);
        }
        // The two tiles: the state of the catalogue, the packages without a docs/ folder.
        // Eight manuals: seven packages with something to read (omnibus/offline has nothing), and the site's own pages.
        self::assertMatchesRegularExpression('/8\s*paquets\s*8\s*manuels/', $dashboard->filter('.app-catalogue')->text());
        self::assertStringContainsString('omnibus/offline', $dashboard->filter('.app-undocumented')->text());

        $screens = array_values(array_unique(array_map(fn ($href) => strtok($href, '?'), array_filter(
            $dashboard->filter('a[href^="/admin/"]')->each(fn ($a) => (string) $a->attr('href')),
            fn ($href) => !str_contains($href, 'logout') && !str_contains($href, '_token='),
        ))));
        self::assertContains('/admin/packages', $screens);
        self::assertContains('/admin/manuals', $screens);
        self::assertContains('/admin/posts', $screens);
        $broken = [];
        foreach ($screens as $screen) {
            $this->client->request('GET', $screen);
            $status = $this->client->getResponse()->getStatusCode();
            if ($status >= 400) {
                $broken[] = $screen.' → '.$status;
            } elseif (200 === $status && !$this->client->getResponse()->headers->has('X-Transparent-Nest')) {
                $broken[] = $screen.' → not in the nest';
            }
        }
        self::assertSame([], $broken, 'every screen opens, in the nest');
    }

    public function testThePackagesAreReadNotTyped(): void
    {
        $this->signIn('glitchr');
        $page = $this->client->request('GET', '/admin/packages');

        self::assertResponseIsSuccessful();
        self::assertStringContainsString('glitchr/omnitrade', $page->filter('body')->text());
        self::assertStringContainsString('Relire les dépôts', $page->filter('body')->text());
        // No way to create, edit or delete one: it comes from its repository.
        foreach (['/admin/packages/new'] as $path) {
            $this->client->request('GET', $path);
            self::assertGreaterThanOrEqual(400, $this->client->getResponse()->getStatusCode(), $path);
        }
        self::assertCount(0, $page->filter('a[href$="/edit"]'));

        // A package's sheet, as the last run wrote it.
        $package = $this->em->getRepository(\App\Entity\Package::class)->findOneBy(['name' => 'omnitrade/paypal']);
        $detail = $this->client->request('GET', '/admin/packages/'.$package->getId());
        self::assertResponseIsSuccessful();
        self::assertStringContainsString('Fournisseur', $detail->filter('body')->text());
        self::assertStringContainsString('omnitrade/paypal', $detail->filter('body')->text());
    }

    public function testTheManualsScreen(): void
    {
        $this->signIn('omniscient');
        $page = $this->client->request('GET', '/admin/manuals');

        self::assertResponseIsSuccessful();
        self::assertResponseHeaderSame('X-Transparent-Nest', 'overlay');
        $text = preg_replace('/\s+/', ' ', $page->filter('body')->text());
        self::assertStringContainsString('8 manuel(s), 9 version(s)', $text);
        self::assertStringContainsString('glitchr/omniscient', $text, 'the site\'s own pages, a manual too');
        self::assertStringContainsString('2.x (3)', $text, 'glitchr/omnibus: its README and two pages');
        self::assertStringContainsString('1.x (2)', $text);

        // "Remplir l'index de recherche": what wikidoc:index does.
        $this->client->submit($page->filter('form[action="/admin/manuals/index"]')->form());
        self::assertResponseRedirects('/admin/manuals');
        self::assertCount(9, FakeTypesense::$collections);
        self::assertStringContainsString('dans 9 collection(s)', $this->client->followRedirect()->filter('body')->text());

        // With the engine stopped the screen says so, and nothing breaks.
        FakeTypesense::$up = false;
        $page = $this->client->request('GET', '/admin/manuals');
        self::assertStringContainsString('index des pages', $page->filter('.admin-header-subtitle')->text());
        $this->client->submit($page->filter('form[action="/admin/manuals/index"]')->form());
        self::assertStringContainsString('Typesense ne répond pas', $this->client->followRedirect()->filter('body')->text());

        // Without its token, nothing runs.
        $this->client->request('POST', '/admin/manuals/sync', ['_token' => 'nope']);
        self::assertResponseStatusCodeSame(403);
    }

    public function testAMemberGetsNoneOfIt(): void
    {
        $this->signIn('lecteur');
        foreach (['/admin', '/admin/packages', '/admin/manuals', '/admin/posts'] as $path) {
            $this->client->request('GET', $path);
            self::assertContains($this->client->getResponse()->getStatusCode(), [302, 403], $path);
        }
    }
}
