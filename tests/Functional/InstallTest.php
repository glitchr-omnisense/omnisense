<?php

namespace App\Tests\Functional;

use App\Tests\Mock\NoNetwork;
use Symfony\Component\BrowserKit\Cookie;

/**
 * The install selector (as pytorch.org/get-started): a GET form of rows - the
 * framework, the channel, what is needed (the core, families and the providers
 * of the chosen ones, ticked as chips or typed by name, a bridge, a prepared
 * structure) - whose answer is the Composer commands, the composer.json block
 * and, in Symfony, the bundles to declare. Each package at its minor version when a release
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
        self::assertSame(['omnibus', 'omnitrade'], $page->filter('input[name="family[]"]')->each(fn ($i) => $i->attr('value')), 'the families, omnibase apart: it is the core');
        self::assertSame('checkbox', $page->filter('input[name="family[]"]')->attr('type'), 'several families can be chosen');
        self::assertSame(['omnitrade'], $page->filter('input[name="family[]"]:checked')->each(fn ($i) => $i->attr('value')));
        // The chosen family's providers are chips, the example's ticked; another family's are in the page, closed and not sent.
        self::assertSame(['omnitrade/paypal', 'omnitrade/stripe'], $page->filter('.om-provider-group:not([hidden]) input[name="provider[]"]')->each(fn ($i) => $i->attr('value')));
        self::assertSame(['omnitrade/stripe'], $page->filter('input[name="provider[]"]:checked')->each(fn ($i) => $i->attr('value')));
        self::assertSame(['omnibus'], $page->filter('.om-provider-group[hidden]')->each(fn ($g) => $g->attr('data-family')));
        self::assertNotNull($page->filter('.om-provider-group[hidden] input')->attr('disabled'));
        self::assertSame('', $page->filter('input[name="providers"]')->attr('value'), 'the provider typed became its chip');
        self::assertSame('paypal stripe', $page->filter('input[name="providers"]')->attr('placeholder'), 'the field shows the family\'s own providers');
        self::assertSame('2 fournisseurs dans omnitrade · 1 choisi', trim($page->filter('.om-provider-count')->text()));
        self::assertCount(0, $page->filter('input[name="p[]"]'));
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
        foreach ($form['provider'] as $chip) {
            if ('omnitrade/stripe' === $chip->availableOptionValues()[0]) {
                $chip->untick();
            }
        }
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

    /**
     * Several families: one `composer require` line with each family's own package and the providers ticked among
     * theirs, every chosen family's providers shown and counted (Marco, 2026-10-18: "allow having multiple families
     * and consequently show the available providers"); old single-family links keep working.
     */
    public function testSeveralFamiliesComposeOneCommand(): void
    {
        $page = $this->client->request('GET', '/install?framework=symfony&channel=stable&need=family&family[]=omnibus&family[]=omnitrade&provider[]=omnibus/offline&provider[]=omnitrade/stripe');
        self::assertResponseIsSuccessful();
        self::assertSame(['omnibus', 'omnitrade'], $page->filter('input[name="family[]"]:checked')->each(fn ($i) => $i->attr('value')));
        $commands = $page->filter('.om-answer-grid pre')->first()->text();
        self::assertSame(1, substr_count($commands, 'composer require'), 'one line for every package');
        self::assertStringContainsString('composer require glitchr/omnibus:2.x-dev glitchr/omnitrade:^1.4 omnibus/offline:', $commands);
        self::assertStringContainsString(' omnitrade/stripe:^1.4', $commands);
        $json = json_decode($page->filter('.om-answer-grid pre')->eq(1)->text(), true);
        self::assertSame(['glitchr/omnibus', 'glitchr/omnitrade', 'omnibus/offline', 'omnitrade/stripe'], array_keys($json['require']));
        self::assertStringContainsString('OmnitradeBundle::class', $page->filter('.om-answer-bundles pre')->text(), 'the bundle of each family that has one');

        // Both families' providers shown, each family counted, the whole counted and the ticked ones.
        self::assertSame(['omnibus', 'omnitrade'], $page->filter('.om-provider-group:not([hidden])')->each(fn ($g) => $g->attr('data-family')));
        self::assertSame(['1', '2'], $page->filter('.om-provider-group:not([hidden]) .om-provider-tally')->each(fn ($t) => $t->text()));
        self::assertSame('3 fournisseurs dans omnibus + omnitrade · 2 choisis', trim($page->filter('.om-provider-count')->text()));
        self::assertSame(['1', '2'], $page->filter('input[name="family[]"] ~ span .om-pick-count')->each(fn ($c) => $c->text()), 'each family\'s count on its pill');
        self::assertSame('offline paypal stripe', $page->filter('input[name="providers"]')->attr('placeholder'));
        self::assertSame('/packages?kind=provider', $page->filter('[data-install-target="browse"]')->attr('href'), 'two families: every provider in the search');

        // A provider of a family not chosen is not installed; a name typed is looked for in every family chosen.
        $require = fn (string $query): array => array_keys(json_decode($this->client->request('GET', '/install?'.$query)->filter('.om-answer-grid pre')->eq(1)->text(), true)['require']);
        self::assertSame(['glitchr/omnibus'], $require('need=family&family[]=omnibus&provider[]=omnitrade/stripe'));
        self::assertSame(['glitchr/omnibus', 'glitchr/omnitrade', 'omnibus/offline', 'omnitrade/paypal'], $require('need=family&family[]=omnitrade&family[]=omnibus&providers=paypal+offline'));

        // An old link (one family, providers typed): the same answer, the providers ticked.
        $page = $this->client->request('GET', '/install?need=family&family=omnibus&providers=offline');
        self::assertSame(['omnibus'], $page->filter('input[name="family[]"]:checked')->each(fn ($i) => $i->attr('value')));
        self::assertSame(['omnibus/offline'], $page->filter('input[name="provider[]"]:checked')->each(fn ($i) => $i->attr('value')));
        self::assertSame('1 fournisseur dans omnibus · 1 choisi', trim($page->filter('.om-provider-count')->text()));
        self::assertSame('offline', $page->filter('input[name="providers"]')->attr('placeholder'), 'never another family\'s providers');

        // No family: said, nothing composed.
        $page = $this->client->request('GET', '/install?need=family&family=');
        self::assertCount(0, $page->filter('input[name="family[]"]:checked'));
        self::assertStringContainsString('Choisissez au moins une famille', $page->filter('.om-provider-count')->text());
        self::assertStringContainsString('Choisissez au moins un paquet', $page->filter('.om-selector-answer')->text());

        // Without script: the form sent again with a second family ticked shows its providers, and ticked ones install.
        $form = $this->client->request('GET', '/install?need=family&family=omnitrade&providers=')->filter('.om-selector')->form();
        $form['family'][0]->tick();
        $page = $this->client->submit($form);
        self::assertSame(['omnibus', 'omnitrade'], $page->filter('.om-provider-group:not([hidden])')->each(fn ($g) => $g->attr('data-family')));
        $form = $page->filter('.om-selector')->form();
        $form['provider'][0]->tick();
        $page = $this->client->submit($form);
        self::assertSame(['glitchr/omnibus', 'glitchr/omnitrade', 'omnibus/offline'], array_keys(json_decode($page->filter('.om-answer-grid pre')->eq(1)->text(), true)['require']));
    }

    public function testTheProvidersAreCountedInEnglishToo(): void
    {
        $this->client->getCookieJar()->set(new Cookie('LANG', 'en'));
        $page = $this->client->request('GET', '/install?need=family&family[]=omnibus&family[]=omnitrade&provider[]=omnibus/offline');
        self::assertSame('en', $page->filter('html')->attr('lang'));
        self::assertSame('3 providers in omnibus + omnitrade · 1 chosen', trim($page->filter('.om-provider-count')->text()));
        self::assertSame('Filter by name', $page->filter('label[for="om-providers"]')->text());
        // The script's templates for the count, in the same words.
        $count = $page->filter('.om-provider-count');
        self::assertSame('%n providers in %f', $count->attr('data-other'));
        self::assertSame('1 provider in %f', $count->attr('data-one'));
        self::assertSame('%s of %n in %f', $count->attr('data-of'));
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

    /**
     * The compatibility section closes the page as on the home: its long title over its lead (a tier's header set them
     * side by side, the lead squeezed into a narrow column), its example's lines wrapping inside their box.
     */
    public function testTheCompatibilitySectionReadsAsOnTheHome(): void
    {
        $page = $this->client->request('GET', '/install');
        self::assertResponseIsSuccessful();
        $section = $page->filter('section#compatibility');
        self::assertCount(1, $section);
        self::assertStringContainsString('om-section', (string) $section->attr('class'));
        self::assertStringNotContainsString('om-tier', (string) $section->attr('class'));
        self::assertCount(1, $section->filter('.om-compat .om-code'));

        $styles = (string) file_get_contents(\dirname(__DIR__, 2).'/assets/styles/layout1.scss');
        self::assertMatchesRegularExpression('/\.om-compat \{.*\.om-code \{[^}]*code \{ white-space: pre-wrap; overflow-wrap: break-word;/s', $styles);
    }
}
