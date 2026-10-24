<?php

namespace App\Tests\Functional;

/** Each package's manual, read from its fetched docs/ at the pinned commit (tests/Fixtures/docs). */
final class DocumentationTest extends SiteTestCase
{
    public function testAManualHomeAndItsContents(): void
    {
        $html = $this->get('/docs/omnitrade/1.x');
        self::assertStringContainsString('<h1>Omnitrade</h1>', $html, 'the first heading is the page\'s title');
        self::assertStringContainsString('href="/docs/omnitrade/1.x/installation"', $html, 'a relative .md link goes to the page');
        self::assertStringContainsString('href="/docs/omnitrade/1.x/guides/webhooks#signatures"', $html, 'its fragment kept');
        self::assertStringContainsString('&lt;script&gt;', $html, 'raw HTML of a package\'s docs is shown, never run');
        self::assertStringNotContainsString("<script>alert('not trusted')</script>", $html);
        self::assertStringContainsString('<code>111111111111</code>', $html, 'the commit read');
        self::assertStringContainsString('Guides', $html, 'a folder is a section of the contents');
    }

    public function testAPageLinksOutsideTheManualToGitHubAtTheCommitRead(): void
    {
        $html = $this->get('/docs/omnitrade/1.x/installation');
        self::assertStringContainsString('<h1>Installation and first calls</h1>', $html, 'the front matter\'s title');
        self::assertStringContainsString('href="https://github.com/glitchr-studio/omnitrade/blob/1111111111111111111111111111111111111111/src/Bridge/Symfony/OmnitradeBundle.php"', $html);
        self::assertStringContainsString('src="https://raw.githubusercontent.com/glitchr-studio/omnitrade/1111111111111111111111111111111111111111/docs/images/flow.png"', $html);
        self::assertStringContainsString('class="doc-callout doc-callout-warning"', $html);
        self::assertStringContainsString('href="/docs/omnitrade/1.x"', $html, 'back to the home page');
        self::assertStringContainsString('href="https://github.com/glitchr-studio/omnitrade/edit/1.x/docs/10-installation.md"', $html, 'the pencil, on the series\' branch');
        self::assertStringContainsString('id="plain-php"', $html);
        self::assertStringContainsString('href="#plain-php"', $html, 'on this page');
        self::assertStringContainsString('rel="next" href="/docs/omnitrade/1.x/guides/webhooks"', $html);
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
}
