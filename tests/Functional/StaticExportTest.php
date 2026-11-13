<?php

namespace App\Tests\Functional;

use Omnibridge\Static\Build\Summary;
use Omnibridge\Static\Console\Cli;
use Omnibridge\Static\Exporter;
use Omnibridge\Static\Render\RequestRenderer;
use Omnibridge\Static\StaticPage;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpFoundation\Request;

/**
 * static.php's incremental export over the tests' site (tests/Fixtures: a catalogue, a manual in two
 * series, news; the site's guides): what its declared inputs let it reuse, and that an incremental
 * export after a change - a manual's page, a piece of news - gives the very files a full export into an
 * empty folder gives.
 */
final class StaticExportTest extends KernelTestCase
{
    private string $dir;
    /** @var array<string, string> fixture file => its content, put back after the test */
    private array $restore = [];

    protected function setUp(): void
    {
        $this->dir = sys_get_temp_dir().'/static-export-test-'.bin2hex(random_bytes(4));
    }

    protected function tearDown(): void
    {
        foreach ($this->restore as $file => $content) {
            file_put_contents($file, $content);
        }
        (new Filesystem())->remove($this->dir);
        parent::tearDown();
    }

    public function testWhatTheInputsDeclare(): void
    {
        $site = $this->site();
        $page = static fn (string $path, string $route, array $parameters = []): StaticPage => new StaticPage($path, $route, $parameters);

        self::assertNotEmpty($site->sharedInputs());
        $manual = $site->inputsOf($page('/docs/omnitrade/1.x', 'app_manual.en', ['key' => 'omnitrade', 'series' => '1.x']));
        self::assertIsArray($manual);
        $fixtures = self::getContainer()->getParameter('kernel.project_dir').'/tests/Fixtures';
        self::assertContains($fixtures.'/docs/omnitrade/'.str_repeat('1', 40), $manual, 'a manual\'s fetched docs/ of each series');
        self::assertContains($fixtures.'/docs/omnitrade/'.str_repeat('2', 40), $manual);
        self::assertSame($manual, $site->inputsOf($page('/fr/docs/omnitrade/1.x/installation', 'app_manual_page.fr', ['key' => 'omnitrade', 'series' => '1.x', 'path' => 'installation'])), 'a former address: its manual\'s inputs');
        self::assertSame($manual, $site->inputsOf($page('/docs/omnitrade/0.9', 'app_manual.en', ['key' => 'omnitrade', 'series' => '0.9'])));
        self::assertSame([$fixtures.'/news/*-first.*.md'], $site->inputsOf($page('/news/first', 'app_news_item.en', ['slug' => 'first'])), 'a piece of news, in every language');
        self::assertSame([$fixtures.'/news'], $site->inputsOf($page('/news', 'app_news.en')));
        self::assertSame([], $site->inputsOf($page('/docs/omnitrade', 'app_manual_root.en', ['key' => 'omnitrade'])));
        foreach ([['/', 'app_index.en'], ['/search.json', 'app_search_index.en'], ['/packages', 'app_packages.en'], ['/docs', 'app_docs.en'], ['/compatibility', 'app_compatibility.en']] as [$path, $route]) {
            self::assertNull($site->inputsOf($page($path, $route)), $path.' reads more than static.php can list: rendered every time');
        }
        self::assertNull($site->inputsOf($page('/docs/nothing/1.x', 'app_manual.en', ['key' => 'nothing', 'series' => '1.x'])), 'an unknown manual');
        self::assertNull($site->inputsOf(new StaticPage('/assets/app.css', role: StaticPage::RESOURCE)), 'a file the pages refer to');
    }

    public function testAnIncrementalExportAfterAChangeIsAFullOne(): void
    {
        $incremental = $this->dir.'/incremental';
        $cache = $this->dir.'/cache';

        $first = $this->export($incremental, $cache);
        self::assertSame(0, $first->reused);

        // Nothing changed: every declared page from the cache, no file written.
        $again = $this->export($incremental, $cache);
        self::assertSame($again->undeclared, $again->rendered, 'only the pages without declared inputs are rendered');
        self::assertGreaterThan(0, $again->reused);
        self::assertSame(0, $again->written);

        // A manual's page and a piece of news change.
        $fixtures = self::getContainer()->getParameter('kernel.project_dir').'/tests/Fixtures';
        $this->change($fixtures.'/docs/omnitrade/'.str_repeat('1', 40).'/10-installation.md', "\nA sentence added by StaticExportTest.\n");
        $this->change($fixtures.'/news/2026-10-01-first.en.md', "\nNews added by StaticExportTest.\n");

        $changed = $this->export($incremental, $cache);
        self::assertGreaterThan($again->rendered, $changed->rendered, 'the pages of that manual and of the news are rendered again');
        self::assertGreaterThan(0, $changed->reused, 'the others are not');
        self::assertGreaterThan(0, $changed->written);

        // The same files as a full export into an empty folder, without a cache.
        $full = $this->export($this->dir.'/full', null, true);
        self::assertSame(0, $full->reused);
        $tree = $this->tree($incremental);
        self::assertSame($this->tree($this->dir.'/full'), $tree);
        self::assertStringContainsString('A sentence added by StaticExportTest.', (string) file_get_contents($incremental.'/docs/omnitrade/1.x/index.html'));
        self::assertStringContainsString('News added by StaticExportTest.', (string) file_get_contents($incremental.'/news/first/index.html'));
    }

    private function site(): \Omnibridge\Static\Site
    {
        self::ensureKernelShutdown();
        $kernel = self::bootKernel();
        $container = self::getContainer();

        return Cli::load($kernel->getProjectDir().'/static.php', [
            Cli::HOST_RENDERER => new RequestRenderer(static fn (Request $request) => $kernel->handle($request), 'http://localhost:8135'),
            Cli::HOST_ROUTES => $container->get('router')->getRouteCollection(),
            Cli::HOST_CONTAINER => $container,
        ]);
    }

    /** One export, by a kernel booted for it (the content services keep what they read). */
    private function export(string $out, ?string $cache, bool $full = false): Summary
    {
        $report = (new Exporter())->export($this->site()->cache($cache), $out, full: $full);
        self::assertNotNull($report->summary);

        return $report->summary;
    }

    private function change(string $file, string $append): void
    {
        $this->restore[$file] ??= (string) file_get_contents($file);
        file_put_contents($file, $this->restore[$file].$append);
    }

    /** @return array<string, string> every file of the folder (its path in it) => its SHA-256 */
    private function tree(string $dir): array
    {
        $files = [];
        foreach (new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS)) as $file) {
            if ($file instanceof \SplFileInfo && $file->isFile()) {
                $files[substr($file->getPathname(), \strlen($dir) + 1)] = (string) hash_file('sha256', $file->getPathname());
            }
        }
        ksort($files);

        return $files;
    }
}
