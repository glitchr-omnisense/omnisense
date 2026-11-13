<?php

namespace App\Tests\Functional;

use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\Filesystem\Filesystem;

/**
 * The deployment manifest the workflow publishes with the site (provenance.json): written by
 * `app:provenance` into an export's folder, from the CI's variables, the lock and the fetched documentation.
 */
final class ProvenanceTest extends KernelTestCase
{
    private string $dir;
    /** @var array<string, mixed> */
    private array $server;

    protected function setUp(): void
    {
        $this->dir = sys_get_temp_dir().'/provenance-test-'.bin2hex(random_bytes(4));
        (new Filesystem())->mkdir($this->dir.'/docs');
        file_put_contents($this->dir.'/omnibridge-static.json', "{\"generator\": \"omnibridge/static\"}\n");
        file_put_contents($this->dir.'/index.html', "<!doctype html><title>Home</title>\n");
        file_put_contents($this->dir.'/docs/index.html', "<!doctype html><title>Docs</title>\n");
        $this->server = $_SERVER;
        $_SERVER = array_merge($_SERVER, [
            'GITHUB_SHA' => 'abcdefabcdefabcdefabcdefabcdefabcdefabcd',
            'GITHUB_REF' => 'refs/heads/main',
            'GITHUB_REPOSITORY' => 'glitchr-omnisense/omnisense',
            'GITHUB_SERVER_URL' => 'https://github.com',
            'GITHUB_RUN_ID' => '123',
            'GITHUB_RUN_ATTEMPT' => '2',
            'GITHUB_EVENT_NAME' => 'repository_dispatch',
        ]);
    }

    protected function tearDown(): void
    {
        $_SERVER = $this->server;
        (new Filesystem())->remove($this->dir);
        parent::tearDown();
    }

    public function testTheManifestNamesWhatTheSiteWasBuiltFrom(): void
    {
        $tester = new CommandTester((new Application(self::bootKernel()))->find('app:provenance'));
        $tester->execute(['folder' => $this->dir, '--summary' => 'incremental: 3 rendered, 9 reused', '--request' => '{"key":"omnitrade","commit":null}']);
        $tester->assertCommandIsSuccessful();

        $data = json_decode((string) file_get_contents($this->dir.'/provenance.json'), true, 512, \JSON_THROW_ON_ERROR);

        self::assertSame(['repository' => 'glitchr-omnisense/omnisense', 'commit' => 'abcdefabcdefabcdefabcdefabcdefabcdefabcd', 'ref' => 'refs/heads/main'], array_intersect_key($data['site'], array_flip(['repository', 'commit', 'ref'])));
        self::assertSame('repository_dispatch', $data['build']['event']);
        self::assertSame(['key' => 'omnitrade', 'commit' => null], $data['build']['request']);
        self::assertSame('https://github.com/glitchr-omnisense/omnisense/actions/runs/123/attempts/2', $data['build']['run']);
        self::assertSame('incremental: 3 rendered, 9 reused', $data['build']['summary']);
        self::assertMatchesRegularExpression('/^\d{4}-\d\d-\d\dT\d\d:\d\d:\d\dZ$/', $data['build']['built']);
        self::assertSame(\PHP_VERSION, $data['tools']['php']);

        // The packages that render the site, at the commit the lock pins.
        $lock = json_decode((string) file_get_contents(self::getContainer()->getParameter('kernel.project_dir').'/composer.lock'), true);
        $static = current(array_filter($lock['packages'], static fn (array $p): bool => 'omnibridge/static' === $p['name']));
        self::assertSame($static['source']['reference'], $data['packages']['omnibridge/static']['reference']);
        self::assertArrayNotHasKey('phpunit/phpunit', $data['packages'], 'require-dev renders nothing');

        // Each documentation source at the commit app:docs:fetch resolved (the tests' manifest).
        self::assertSame(['repository' => 'glitchr-omnisense/omnitrade', 'ref' => str_repeat('1', 40), 'commit' => str_repeat('1', 40)], $data['documentation']['omnitrade']['1.x']);

        // The export's checksums: its report, and every file but this one.
        self::assertSame(hash_file('sha256', $this->dir.'/omnibridge-static.json'), $data['artifact']['report']);
        $lines = [];
        foreach (['docs/index.html', 'index.html', 'omnibridge-static.json'] as $file) {
            $lines[] = $file.' '.hash_file('sha256', $this->dir.'/'.$file);
        }
        self::assertSame(3, $data['artifact']['files']);
        self::assertSame(hash('sha256', implode("\n", $lines)), $data['artifact']['sha256']);

        // Written again (the next run): the same but for the build's own values.
        $first = $data;
        $tester->execute(['folder' => $this->dir]);
        $second = json_decode((string) file_get_contents($this->dir.'/provenance.json'), true);
        unset($first['build'], $second['build']);
        self::assertSame($first, $second);
    }

    public function testAFolderWithoutAnExportIsRefused(): void
    {
        unlink($this->dir.'/omnibridge-static.json');
        $tester = new CommandTester((new Application(self::bootKernel()))->find('app:provenance'));

        self::assertSame(1, $tester->execute(['folder' => $this->dir]));
    }
}
