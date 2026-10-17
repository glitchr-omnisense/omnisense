<?php

namespace App\Tests\Functional;

use App\Tests\Mock\NoNetwork;

/**
 * The install selector (as pytorch.org/get-started): a GET form of rows - the
 * framework, the channel, what is needed (the core, a family and the providers
 * typed by name, a bridge, a prepared structure) - whose answer is the Composer
 * commands, the composer.json block and, in Symfony, the bundles to declare;
 * no provider is listed. Each package at its minor version when a release
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
        // What is needed: the foundation (the core, a family, a bridge), and apart the optional prepared structure.
        self::assertSame(['core', 'family', 'bridge', 'structure'], $page->filter('input[name="need"]')->each(fn ($i) => $i->attr('value')));
        self::assertSame('family', $page->filter('input[name="need"]:checked')->attr('value'));
        self::assertSame(['omnibus', 'omnitrade'], $page->filter('input[name="family"]')->each(fn ($i) => $i->attr('value')), 'the families, omnibase apart: it is the core');
        self::assertSame('omnitrade', $page->filter('input[name="family"]:checked')->attr('value'));
        // The providers are typed, never listed: the page names none but the one chosen.
        self::assertSame('stripe', $page->filter('input[name="providers"]')->attr('value'));
        self::assertCount(0, $page->filter('input[name="p[]"]'));
        $html = $page->filter('#content')->html();
        self::assertStringNotContainsString('omnitrade/paypal', $html);
        self::assertStringNotContainsString('omnibus/offline', $html);
        self::assertSame('/packages?family=omnitrade&kind=provider', $page->filter('[data-install-target="browse"]')->attr('href'));
        // The rows of the other needs are there for the script, closed.
        self::assertSame(['bridge', 'structure'], $page->filter('.om-selector-row[hidden]')->each(fn ($r) => $r->attr('data-need')));

        $commands = $page->filter('.om-answer-grid pre')->first()->text();
        self::assertStringContainsString('composer require glitchr/omnitrade:^1.4 omnitrade/stripe:^1.4', $commands);
        self::assertStringContainsString('composer config repositories.glitchr-omnitrade vcs https://github.com/glitchr-studio/omnitrade', $commands, 'not on Packagist (never asked): its repository is declared');
        $json = json_decode($page->filter('.om-answer-grid pre')->eq(1)->text(), true);
        self::assertSame(['glitchr/omnitrade' => '^1.4', 'omnitrade/stripe' => '^1.4'], $json['require']);
        self::assertArrayNotHasKey('minimum-stability', $json, 'two releases: nothing to loosen');
        self::assertSame('vcs', $json['repositories'][0]['type']);
        self::assertCount(0, $page->filter('.om-answer-bundles'), 'no bundle to declare in plain PHP');
        self::assertStringContainsString('registre de la famille se monte à la main', $page->filter('.om-answer-notes')->text());
        self::assertSame([], NoNetwork::$requests);

        // Choose: Symfony, the development channel, a family and a never-released provider typed by name.
        $form = $page->filter('.om-selector')->form();
        $form['framework']->select('symfony');
        $form['channel']->select('dev');
        $form['providers'] = 'PayPal';
        $page = $this->client->submit($form);
        self::assertResponseIsSuccessful();
        self::assertStringContainsString('framework=symfony', $this->client->getRequest()->getRequestUri());

        $json = json_decode($page->filter('.om-answer-grid pre')->eq(1)->text(), true);
        self::assertSame(['glitchr/omnitrade' => '1.x-dev', 'omnitrade/paypal' => 'dev-main'], $json['require'], 'the dev channel follows each branch');
        self::assertSame('dev', $json['minimum-stability']);
        self::assertTrue($json['prefer-stable']);
        self::assertStringContainsString('composer config minimum-stability dev', $page->filter('.om-answer-grid pre')->first()->text());
        // In Symfony, the family's bundle line, read in its package (Bridge/Symfony): there is no Flex recipe.
        self::assertSame("Omnitrade\\Bridge\\Symfony\\OmnitradeBundle::class => ['all' => true],", $page->filter('.om-answer-bundles pre')->text());
        self::assertStringContainsString('config/bundles.php', $page->filter('.om-answer-notes')->text());
        // Where each constraint comes from, linked to the package's versions.
        self::assertSame(['glitchr/omnitrade', 'omnitrade/paypal'], $page->filter('.om-answer-table tbody a')->each(fn ($a) => $a->text()));
        self::assertStringContainsString('branche de développement', $page->filter('.om-answer-table tbody tr')->eq(1)->text());
        self::assertSame('/packages/omnitrade/paypal#versions', $page->filter('.om-answer-table tbody a')->last()->attr('href'));
    }

    public function testEachNeedComposesItsCommand(): void
    {
        $require = fn (string $query): array => json_decode($this->client->request('GET', '/install?'.$query)->filter('.om-answer-grid pre')->eq(1)->text(), true)['require'];

        // The core: omnibase and its Composer plugin, in Symfony.
        $page = $this->client->request('GET', '/install?framework=symfony&channel=dev&need=core');
        $json = json_decode($page->filter('.om-answer-grid pre')->eq(1)->text(), true);
        self::assertSame(['glitchr/omnibase' => '3.x-dev'], $json['require']);
        self::assertSame(['glitchr/base-plugin' => true], $json['config']['allow-plugins'], 'omnibase requires its Composer plugin');
        self::assertCount(0, $page->filter('.om-answer-bundles'), 'omnibase is no family: nothing to declare by hand');
        self::assertSame('core', $page->filter('input[name="need"]:checked')->attr('value'));
        self::assertSame(['family', 'family', 'bridge', 'structure'], $page->filter('.om-selector-row[hidden]')->each(fn ($r) => $r->attr('data-need')));

        // Another family, two providers typed by name (or whole name), the rest of the family left out.
        self::assertSame(['glitchr/omnibus', 'omnibus/offline'], array_keys($require('need=family&family=omnibus&providers=offline')));
        self::assertSame(['glitchr/omnitrade' => '^1.4', 'omnitrade/paypal' => 'dev-main', 'omnitrade/stripe' => '^1.4'], $require('need=family&family=omnitrade&providers=omnitrade/stripe,+paypal'));
        // The family alone.
        self::assertSame(['glitchr/omnitrade' => '^1.4'], $require('need=family&family=omnitrade&providers='));

        // A provider the family does not have: said, and the rest answers.
        $page = $this->client->request('GET', '/install?need=family&family=omnitrade&providers=stripe+nope');
        self::assertStringContainsString('Aucun fournisseur de omnitrade ne s’appelle nope.', $page->filter('.om-selector-answer .om-install-note')->text());
        self::assertSame(['glitchr/omnitrade' => '^1.4', 'omnitrade/stripe' => '^1.4'], json_decode($page->filter('.om-answer-grid pre')->eq(1)->text(), true)['require']);

        // A prepared structure: optional, Symfony today - refused elsewhere, with the reason.
        self::assertSame(['omnibase/blog' => 'dev-main'], $require('framework=symfony&need=structure&structure=omnibase/blog'));
        $page = $this->client->request('GET', '/install?framework=bare&need=structure&structure=omnibase/blog');
        self::assertStringContainsString('omnibase/blog : pour PHP nu, pas encore', $page->filter('.om-install-note')->text());
        self::assertStringContainsString('facultative', $page->filter('.om-selector-row.is-optional')->text());
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
        self::assertStringContainsString('structures préparées', $page->filter('.om-install-note')->text());
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
