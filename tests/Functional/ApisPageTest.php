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
        self::assertStringContainsString('data-kind="family" data-family="omnilang" data-status="wanted"', $html);
        self::assertStringContainsString('data-capabilities="translate detect glossary documents"', $html);
        // A provider and its policy.
        self::assertStringContainsString('<code>omnilang/deepl</code>', $html);
        self::assertStringContainsString('href="https://www.deepl.com/en/pro-license"', $html);
        self::assertStringContainsString('<q>Photo by {name} on Unsplash</q>', $html, 'the credit, verbatim');
        // A released provider from the catalogue (the tests' omnitrade): its manual, no policy declared yet.
        self::assertStringContainsString('<code>omnitrade/stripe</code>', $html);
        self::assertStringContainsString('Usage policy not declared yet.', $html);
        // A proposal opens the site's issue template, its title filled in.
        self::assertStringContainsString('href="https://github.com/glitchr-omnisense/omnisense/issues/new?template=provider.yml&amp;title=%5Bomnilang%2Fdeepl%5D%20"', $html);

        $fr = $this->get('/fr/api');
        self::assertStringContainsString('Toutes les familles, toutes les API', $fr);
        self::assertStringContainsString('Mainteneur recherché', $fr);
        self::assertStringContainsString('Traduire un texte ou une page, quel que soit le moteur.', $fr, 'a family\'s phrase, in the page\'s language');
        self::assertStringContainsString('<li>Reconnaître la langue</li>', $fr, 'a card\'s capability, in the page\'s language');
        self::assertStringContainsString('Offre gratuite', $fr);
    }

    public function testTheCallListsWhatIsWantedFromTheSameData(): void
    {
        $html = $this->get('/contribute');
        self::assertStringContainsString('For developers', $html);
        self::assertStringContainsString('For the companies behind the APIs', $html);
        self::assertStringContainsString('href="/apis#family-omnical"', $html, 'a wanted family leads to its filtered cards');
        self::assertStringContainsString('href="/apis#family-omnistate.help-1"', $html, 'a released family with providers wanted');
        self::assertStringContainsString('issues/new?template=api-provider.yml', $html);
        self::assertStringNotContainsString('omnisense.local', $html, 'no contact address that does not exist');

        $fr = $this->get('/fr/contribuer');
        self::assertStringContainsString('Pour les entreprises qui tiennent les API', $fr);
        self::assertStringContainsString('Lire et écrire un agenda, et connaître les jours fériés.', $fr);
        self::assertStringContainsString('href="/contribute"', $this->get('/support'));
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
