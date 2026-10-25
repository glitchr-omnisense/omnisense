<?php

namespace App\Tests\Functional;

use Symfony\Component\Yaml\Yaml;

/** Each package's manual, read from its fetched docs/ at the pinned commit (tests/Fixtures/docs). */
final class DocumentationTest extends SiteTestCase
{
    public function testAPackagesWholeManualIsOnePage(): void
    {
        $html = $this->get('/docs/omnitrade/1.x');
        self::assertStringContainsString('<span class="manual-title-of">omniscient/omnitrade</span> Omnitrade</h1>', $html, 'the home\'s first heading is the page\'s title');
        self::assertStringContainsString('<h2 id="installation" class="doc-part-title">Installation and first calls', $html, 'each page a part, its front matter\'s title at the second level');
        self::assertStringContainsString('<h2 id="guides-webhooks" class="doc-part-title">Webhooks', $html, 'a page of a folder');
        self::assertStringContainsString('<h3 id="installation-plain-php">Plain PHP', $html, 'a page\'s headings one level down, their ids prefixed');
        self::assertStringContainsString('href="#installation"', $html, 'a relative .md link goes to its part');
        self::assertStringContainsString('href="#guides-webhooks-signatures"', $html, 'its fragment kept, prefixed');
        self::assertStringContainsString('&lt;script&gt;', $html, 'raw HTML of a package\'s docs is shown, never run');
        self::assertStringNotContainsString("<script>alert('not trusted')</script>", $html);
        self::assertStringContainsString('<code>111111111111</code>', $html, 'the commit read');
        self::assertStringContainsString('href="https://github.com/glitchr-studio/omnitrade/blob/1111111111111111111111111111111111111111/src/Bridge/Symfony/OmnitradeBundle.php"', $html, 'a file of the repository at the commit read');
        self::assertStringContainsString('src="https://raw.githubusercontent.com/glitchr-studio/omnitrade/1111111111111111111111111111111111111111/docs/images/flow.png"', $html);
        self::assertStringContainsString('class="doc-callout doc-callout-warning"', $html);
        self::assertStringContainsString('href="https://github.com/glitchr-studio/omnitrade/edit/1.x/docs/10-installation.md"', $html, 'each part\'s pencil, on the series\' branch');
        self::assertStringContainsString('<nav class="omnidocs-toc" data-omnidocs-toc', $html, 'the sidebar\'s table of contents');
        self::assertMatchesRegularExpression('#<li><a href="\#installation">Installation and first calls</a>\s*<ul>\s*<li><a href="\#installation-plain-php">Plain PHP</a></li>#', $html);
        self::assertStringContainsString('data-omnidocs-toc-toggle aria-controls="manual-side"', $html, 'the contents\' button, on a phone');
        self::assertStringContainsString('Stripe', $html, 'the family\'s core lists its providers');
        self::assertStringContainsString('href="/packages/omnitrade/stripe"', $html, 'a provider without a manual: its sheet');
        self::assertStringContainsString('class="om-fact is-', $html, 'the header says what the sheet said');
    }

    public function testAPagesFormerAddressRedirectsToItsPartTheAnchorCarriedOver(): void
    {
        $this->get('/docs/omnitrade/1.x/installation', 301);
        $response = $this->client->getResponse();
        self::assertSame('/docs/omnitrade/1.x#installation', $response->headers->get('Location'));
        self::assertSame('installation-', $response->headers->get('X-Static-Fragment'), '/installation#plain-php lands on #installation-plain-php');
        $this->get('/fr/docs/omnitrade/1.x/guides/webhooks', 301);
        self::assertSame('/fr/docs/omnitrade/1.x#guides-webhooks', $this->client->getResponse()->headers->get('Location'));
    }

    public function testEachPackageHasItsOwnSeriesAndLatestIsARedirection(): void
    {
        $old = $this->get('/docs/omnitrade/0.9');
        self::assertStringContainsString('The old series.', $old);
        self::assertStringContainsString('the current one is 1.x', $old);
        self::assertStringContainsString('no longer maintained', $old);

        $this->get('/docs/omnitrade/latest', 302);
        self::assertSame('/docs/omnitrade/1.x', $this->client->getResponse()->headers->get('Location'));
        $this->get('/docs/omnitrade', 301);
        self::assertSame('/docs/omnitrade/1.x', $this->client->getResponse()->headers->get('Location'));
        $this->get('/fr/docs/omnitrade/latest', 302);
        self::assertSame('/fr/docs/omnitrade/1.x', $this->client->getResponse()->headers->get('Location'));

        $this->get('/docs/omnitrade/2.x', 404);
        $this->get('/docs/omnitrade/1.x/nothing', 404);
        $this->get('/docs/nothing/1.x', 404);
    }

    public function testTheVersionSelectorAndOnlyTheCurrentSeriesIsIndexed(): void
    {
        $current = $this->get('/docs/omnitrade/1.x');
        self::assertMatchesRegularExpression('#<link rel="canonical" href="https?://[^/"]+/docs/omnitrade/1\.x">#', $current, 'the current series is canonical');
        self::assertStringNotContainsString('noindex', $current);
        self::assertStringContainsString('<a href="/docs/omnitrade/1.x" data-series="1.x" aria-current="true">1.x <small>current</small></a>', $current, 'the selector marks the series read and the current one');
        self::assertStringContainsString('<a href="/docs/omnitrade/0.9" data-series="0.9">0.9</a>', $current);
        self::assertStringContainsString('head of the branch 1.x when pinned', $current, 'the manifest says the ref is a branch head');

        $old = $this->get('/docs/omnitrade/0.9');
        self::assertStringContainsString('<meta name="robots" content="noindex, follow">', $old, 'an older series is not indexed');
        self::assertStringNotContainsString('rel="canonical"', $old, 'nor canonical: its content is not the current series\'');
        self::assertStringContainsString('You are reading the 0.9 series; the current one is 1.x.', $old);
        self::assertStringContainsString('<a href="/docs/omnitrade/1.x">Read 1.x</a>', $old);

        $french = $this->get('/fr/docs/omnitrade/0.9');
        self::assertStringContainsString('<a href="/fr/docs/omnitrade/1.x" data-series="1.x">1.x <small>actuelle</small></a>', $french);
        self::assertStringContainsString('noindex, follow', $french);
    }

    /** The site's own manifest: every series pinned at a full commit, said to be a branch head or a tag, its default among its series. */
    public function testTheManifestPinsEverySeries(): void
    {
        $manuals = Yaml::parseFile(\dirname(__DIR__, 2).'/config/documentation.yaml')['packages'];
        foreach ($manuals as $key => $manual) {
            self::assertArrayHasKey((string) $manual['default'], $manual['versions'], $key.': its default is one of its series');
            foreach ($manual['versions'] as $series => $version) {
                self::assertMatchesRegularExpression('/^[0-9a-f]{40}$/', $version['ref'], $key.' '.$series);
                self::assertContains($version['pinned'] ?? null, ['branch-head', 'tag'], $key.' '.$series);
                self::assertSame((string) $series, (string) $version['branch'], $key.': a series follows the branch of its name');
            }
        }
        self::assertSame(['4.x', '3.x'], array_map('strval', array_keys($manuals['omnibase']['versions'])), 'omnibase\'s two series with a docs/ folder');
        self::assertSame('4.x', $manuals['omnibase']['default']);
    }
}
