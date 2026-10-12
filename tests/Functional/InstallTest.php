<?php

namespace App\Tests\Functional;

use App\Tests\Mock\NoNetwork;

/**
 * The install selector: a GET form whose answer is the Composer command and
 * the composer.json block, each package at its minor version when a release
 * exists (^1.4), else at its development branch (2.x-dev) - with the
 * stability, the vcs repositories and the plugin that go with them. Without
 * JavaScript the page is sent again; with it, the answer alone is asked
 * (?fragment=1). The versions come from the repositories (FakeVersions in
 * the tests), the registries are never asked.
 */
class InstallTest extends SiteTestCase
{
    public function testTheSelectorAnswersWithoutScript(): void
    {
        // The example: omnitrade and its Stripe provider, both released; the fixtures never asked Packagist, so their repositories are declared.
        $page = $this->client->request('GET', '/install');
        self::assertResponseIsSuccessful();
        self::assertSame('Composer son installation', $page->filter('h1')->text());
        self::assertSame(['bare', 'symfony', 'laravel'], $page->filter('input[name="framework"]')->each(fn ($i) => $i->attr('value')));
        self::assertSame('bare', $page->filter('input[name="framework"]:checked')->attr('value'));
        self::assertSame(['glitchr/omnibus', 'glitchr/omnitrade'], $page->filter('#om-row-families ~ div input')->each(fn ($i) => $i->attr('value')), 'the families, omnibase apart');
        // omnibase and its components are Symfony bundles: their row is closed in plain PHP.
        self::assertCount(1, $page->filter('.om-selector-row.is-off'));
        self::assertCount(1, $page->filter('input[value="glitchr/omnibase"][disabled]'));

        $commands = $page->filter('.om-answer-grid pre')->first()->text();
        self::assertStringContainsString('composer require glitchr/omnitrade:^1.4 omnitrade/stripe:^1.4', $commands);
        self::assertStringContainsString('composer config repositories.glitchr-omnitrade vcs https://github.com/glitchr-studio/omnitrade', $commands, 'not on Packagist (never asked): its repository is declared');
        $json = json_decode($page->filter('.om-answer-grid pre')->eq(1)->text(), true);
        self::assertSame(['glitchr/omnitrade' => '^1.4', 'omnitrade/stripe' => '^1.4'], $json['require']);
        self::assertArrayNotHasKey('minimum-stability', $json, 'two releases: nothing to loosen');
        self::assertSame('vcs', $json['repositories'][0]['type']);
        self::assertStringContainsString('registre de la famille se monte à la main', $page->filter('.om-answer-notes')->text());
        self::assertSame([], NoNetwork::$requests);

        // Choose: Symfony (so that omnibase's row opens), the development channel, a never-released provider and the foundation.
        $form = $this->client->request('GET', '/install?framework=symfony')->filter('.om-selector')->form();
        $form['channel']->select('dev');
        foreach ($form->get('p') as $box) {
            \in_array($box->availableOptionValues()[0], ['glitchr/omnitrade', 'omnitrade/paypal', 'glitchr/omnibase'], true) ? $box->tick() : $box->untick();
        }
        $page = $this->client->submit($form);
        self::assertResponseIsSuccessful();
        self::assertStringContainsString('framework=symfony', $this->client->getRequest()->getRequestUri());

        $json = json_decode($page->filter('.om-answer-grid pre')->eq(1)->text(), true);
        self::assertSame(['glitchr/omnibase' => '3.x-dev', 'glitchr/omnitrade' => '1.x-dev', 'omnitrade/paypal' => 'dev-main'], $json['require'], 'the dev channel follows each branch');
        self::assertSame('dev', $json['minimum-stability']);
        self::assertTrue($json['prefer-stable']);
        self::assertSame(['glitchr/base-plugin' => true], $json['config']['allow-plugins'], 'omnibase requires its Composer plugin');
        self::assertStringContainsString('composer config minimum-stability dev', $page->filter('.om-answer-grid pre')->first()->text());
        self::assertStringContainsString('config/bundles.php', $page->filter('.om-answer-notes')->text());
        // Where each constraint comes from, linked to the package's versions.
        self::assertSame(['glitchr/omnibase', 'glitchr/omnitrade', 'omnitrade/paypal'], $page->filter('.om-answer-table tbody a')->each(fn ($a) => $a->text()));
        self::assertStringContainsString('branche de développement', $page->filter('.om-answer-table tbody tr')->eq(2)->text());
        self::assertSame('/packages/omnitrade/paypal#versions', $page->filter('.om-answer-table tbody a')->last()->attr('href'));
    }

    public function testStableWhereNothingIsReleasedFallsBackToTheBranch(): void
    {
        $page = $this->client->request('GET', '/install?framework=bare&channel=stable&p[]=omnitrade/paypal&p[]=glitchr/omnibus');
        $json = json_decode($page->filter('.om-answer-grid pre')->eq(1)->text(), true);

        self::assertSame(['glitchr/omnibus' => '2.x-dev', 'omnitrade/paypal' => 'dev-main'], $json['require']);
        self::assertSame('dev', $json['minimum-stability'], 'a development version somewhere: the project has to accept it');
        self::assertStringContainsString('minimum-stability dev', $page->filter('.om-answer-notes')->text());
    }

    public function testWhatDoesNotFitTheFrameworkIsRefusedAndSaid(): void
    {
        // omnibase in plain PHP: refused, with the reason; the rest answers.
        $page = $this->client->request('GET', '/install?framework=laravel&p[]=glitchr/omnibase&p[]=omnibase/blog&p[]=glitchr/omnitrade');
        self::assertResponseIsSuccessful();
        self::assertStringContainsString('glitchr/omnibase, omnibase/blog', $page->filter('.om-install-note')->text());
        self::assertStringContainsString('pas encore', $page->filter('.om-install-note')->text());
        self::assertSame(['glitchr/omnitrade' => '^1.4'], json_decode($page->filter('.om-answer-grid pre')->eq(1)->text(), true)['require']);
        self::assertStringContainsString('Pas encore de pont Laravel', $page->filter('.om-answer-notes')->text());

        // Nothing chosen, or nothing that exists: the page says so, without error.
        $page = $this->client->request('GET', '/install?p[]=glitchr/nothing');
        self::assertResponseIsSuccessful();
        self::assertStringContainsString('Choisissez au moins un paquet', $page->filter('.om-selector-answer')->text());
        // An npm library is not a Composer package.
        $page = $this->client->request('GET', '/install?p[]=@glitchr/flowjs');
        self::assertStringContainsString('Choisissez au moins un paquet', $page->filter('.om-selector-answer')->text());
    }

    public function testTheFragmentForTheScript(): void
    {
        $this->client->request('GET', '/install?fragment=1&framework=symfony&channel=stable&p[]=omnitrade/stripe');
        self::assertResponseIsSuccessful();
        $html = (string) $this->client->getResponse()->getContent();
        self::assertStringNotContainsString('<html', $html, 'the answer alone');
        self::assertStringContainsString('composer require omnitrade/stripe:^1.4', $html);
    }
}
