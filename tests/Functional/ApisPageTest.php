<?php

namespace App\Tests\Functional;

/** The cards of /apis, the call of /contribute and the header's star, as a visitor - and the static export - reads them. */
final class ApisPageTest extends SiteTestCase
{
    public function testTheCardsAreAPlainListTheFiltersEnhance(): void
    {
        $html = $this->get('/apis');
        // Every card is in the page; the filters are shown by apis_controller.js.
        self::assertStringContainsString('data-controller="apis"', $html);
        self::assertMatchesRegularExpression('/<form class="om-finder om-api-filters"[^>]* hidden/', $html, 'without JavaScript, no filter that would do nothing');
        self::assertStringContainsString('id="card-omnilang"', $html, 'a family wanted, from config/apis.yaml');
        self::assertStringContainsString('data-kind="family" data-family="omnilang" data-status="available"', $html, 'built on 2026-11-10');
        self::assertStringContainsString('data-kind="family" data-family="omnisport" data-status="wanted"', $html, 'open to contributors, not reserved');
        // Marco's merges of 2026-11-10: no omniproof, omnitally, omnireview or omnifund card of their own.
        foreach (['omniproof', 'omnitally', 'omnireach', 'omnireview', 'omnifund'] as $merged) {
            self::assertStringNotContainsString('id="card-'.$merged.'"', $html, $merged);
        }
        self::assertStringContainsString('<code>omnisign/stripe-identity</code>', $html, 'identity verification is omnisign\'s');
        self::assertStringContainsString('<code>omnibill/pennylane</code>', $html, 'accounting is omnibill\'s');
        self::assertStringContainsString('approved e-invoicing platform (PDP)', $html);
        self::assertStringContainsString('id="card-omnipulse"', $html);
        self::assertStringContainsString('Audience, reviews and funding: the pulse of a site or project.', $html);
        self::assertStringContainsString('Merged into it on 2026-11-10: omnireach, omnireview, omnifund', $html);
        self::assertStringContainsString('data-capabilities="translate detect glossary documents"', $html);
        // A provider wanted, and the policy the site read on its pages.
        self::assertStringContainsString('<code>omnilang/deepl</code>', $html);
        self::assertStringContainsString('href="https://unsplash.com/api-terms"', $html);
        self::assertStringContainsString('<q>Photo by {name} on Unsplash</q>', $html, 'the credit, verbatim');
        self::assertStringContainsString('This site, from the provider’s pages', $html, 'who declared it');
        // A released provider that declares its policy (the tests' omnitrade/stripe, tests/Fixtures/policies.json).
        self::assertStringContainsString('<code>omnitrade/stripe</code>', $html);
        self::assertStringContainsString('100 requests a second per key', $html, 'its limit in plain words');
        self::assertStringContainsString('Its maintainer', $html);
        self::assertStringContainsString('verified 2026-11-10', $html);
        self::assertStringContainsString('href="https://stripe.com/legal/ssa"', $html, 'the terms');
        self::assertMatchesRegularExpression('#<i>Cache</i> <span class="om-fact is-unknown">Unknown</span>#', $html, 'unknown is shown as unknown');
        self::assertStringContainsString('data-auth="key" data-commercial="allowed"', $html);
        // One that declares none (omnitrade/paypal): said so.
        self::assertStringContainsString('Usage policy not declared yet.', $html);
        self::assertStringContainsString('data-auth="undeclared" data-commercial="undeclared"', $html);
        // A proposal opens the site's issue template, its title filled in.
        self::assertStringContainsString('href="https://github.com/glitchr-omnisense/omnisense/issues/new?template=provider.yml&amp;title=%5Bomnijob%2Fadzuna%5D%20"', $html);
        self::assertStringContainsString('href="/apis/policies"', $html, 'the policies, in one table');

        $fr = $this->get('/fr/api');
        self::assertStringContainsString('Toutes les familles, toutes les API', $fr);
        self::assertStringContainsString('Mainteneur recherché', $fr);
        self::assertStringContainsString('Traduire un texte ou une page, quel que soit le moteur.', $fr, 'a family\'s phrase, in the page\'s language');
        self::assertStringContainsString('<li>Reconnaître la langue</li>', $fr, 'a card\'s capability, in the page\'s language');
        self::assertStringContainsString('100 requêtes par seconde par clé', $fr, 'a limit, in the page\'s language');
        self::assertStringContainsString('Son mainteneur', $fr);
    }

    public function testTheCallListsWhatIsWantedFromTheSameData(): void
    {
        $html = $this->get('/contribute');
        self::assertStringContainsString('For developers', $html);
        self::assertStringContainsString('For the companies behind the APIs', $html);
        self::assertStringContainsString('href="/apis#family-omnijob"', $html, 'a wanted family leads to its filtered cards');
        self::assertStringNotContainsString('href="/apis#family-omnical"', $html, 'built: no longer wanted');
        // The declarative provider and the policy, each with its manual.
        self::assertStringContainsString('href="/docs/omnical/1.x/declarative"', $html);
        self::assertStringContainsString('href="/docs/contracts/1.x/declare-your-policy"', $html);
        self::assertStringContainsString('"declaredBy": "provider"', $html, 'a policy.json sample');
        self::assertStringContainsString('href="/apis#family-omnilab.help-1"', $html, 'a released family with providers wanted');
        self::assertStringContainsString('issues/new?template=api-provider.yml', $html);
        self::assertStringNotContainsString('omnisense.local', $html, 'no contact address that does not exist');
        foreach (['/privacy', '/legal', '/fr/confidentialite'] as $page) {
            $text = $this->get($page);
            self::assertStringNotContainsString('omnisense.local', $text, $page);
            self::assertStringContainsString('href="https://github.com/glitchr-omnisense/omnisense/issues"', $text, $page.': the issues are the contact');
        }

        $fr = $this->get('/fr/contribuer');
        self::assertStringContainsString('Pour les entreprises qui tiennent les API', $fr);
        self::assertStringContainsString('Lire les offres d’emploi des sites d’emploi.', $fr);
        self::assertStringContainsString('un fournisseur en fichier de données', $fr);
        self::assertStringContainsString('href="/contribute"', $this->get('/support'));
    }

    public function testThePoliciesPageListsEveryProviderAndItsPolicy(): void
    {
        $html = $this->get('/apis/policies');
        self::assertStringContainsString('<h1>Usage policies</h1>', $html);
        self::assertStringContainsString('data-controller="policies"', $html);
        self::assertMatchesRegularExpression('/<form class="om-finder om-api-filters"[^>]* hidden/', $html, 'without JavaScript, the plain table');
        // A row per provider, with what the filters read.
        self::assertStringContainsString('id="policy-omnitrade-stripe" data-family="omnitrade" data-commercial="allowed"', $html);
        self::assertStringContainsString('id="policy-omnitrade-paypal" data-family="omnitrade" data-commercial="undeclared"', $html);
        self::assertStringContainsString('id="policy-omnisport-openf1" data-family="omnisport" data-commercial="forbidden"', $html, 'a typed policy: non-commercial only');
        self::assertStringContainsString('<input type="checkbox" name="commercial" value="unknown"', $html);
        self::assertStringContainsString('<input type="checkbox" name="family" value="omnical"', $html);
        // The declared one: its limit, its rights, who declared it, the file it was read from.
        self::assertStringContainsString('100 requests a second per key', $html);
        self::assertStringContainsString('<span class="om-fact is-partial">Under contract</span>', $html, 'storage: under contract');
        self::assertStringContainsString('<span class="om-fact is-no">Forbidden</span>', $html, 'redistribution: forbidden');
        self::assertStringContainsString('href="https://github.com/glitchr-omnisense/omnitrade-stripe/blob/1.x/policy.json"', $html);
        self::assertStringContainsString('href="/docs/contracts/1.x/declare-your-policy"', $html);

        $fr = $this->get('/fr/api/politiques');
        self::assertStringContainsString('<h1>Politiques d’usage</h1>', $fr);
        self::assertStringContainsString('Non commercial seulement', $fr);
    }

    public function testTheHeaderShowsTheStarWithoutACountWhenNoneWasRead(): void
    {
        // The tests' app.github_file does not exist: the button alone, still a link to star the repository.
        $html = $this->get('/');
        self::assertStringContainsString('class="om-tool om-star" href="https://github.com/glitchr-omnisense/omnisense"', $html);
        self::assertStringContainsString('<span class="om-star-label">Star</span>', $html);
        self::assertStringNotContainsString('om-star-count', $html);
        self::assertStringContainsString('href="/apis"', $html, 'the cards, in the header');
    }
}
