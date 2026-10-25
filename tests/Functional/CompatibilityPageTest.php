<?php

namespace App\Tests\Functional;

/** The compatibility dashboard and the providers' guide, as a visitor - and the static export - reads them. */
final class CompatibilityPageTest extends SiteTestCase
{
    public function testTheDashboardShowsWhatTheDataFileAndTheCatalogueSay(): void
    {
        $html = $this->get('/compatibility');
        self::assertStringContainsString('<th scope="col">PHP 8.4</th>', $html, 'a column per CI job label');
        self::assertMatchesRegularExpression('#<tr data-name="omnitrade/stripe" data-conformance="passes">.*?href="https://github.com/glitchr-studio/omnitrade-stripe/actions/runs/12"#s', $html);
        self::assertStringContainsString('data-name="omnitrade/paypal" data-conformance="fails"', $html, 'its CI failed');
        self::assertStringContainsString('data-name="omniscient/omnitrade" data-conformance="none"', $html, 'a family\'s core is no provider');
        self::assertStringContainsString('Read from GitHub on 2026-10-25 12:00 UTC', $html);
        self::assertStringContainsString('Not verified for real', $html, 'omnitrade/paypal\'s README, from the catalogue');
        self::assertStringContainsString('href="/compatibility"', $this->get('/packages'));

        $fr = $this->get('/fr/compatibilite');
        self::assertStringContainsString('lang="fr"', $fr);
        self::assertStringContainsString('branchée, CI verte', $fr);
    }

    public function testTheProvidersGuideIsInBothLanguages(): void
    {
        self::assertStringContainsString('omni-provider-template', $this->get('/guides/providers'));
        self::assertStringContainsString('Écrire un provider', $this->get('/fr/guides/providers'));
    }
}
