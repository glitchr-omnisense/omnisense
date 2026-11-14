<?php

namespace App\Tests\Functional;

use App\Catalogue\Catalogue;
use App\Catalogue\RepositoryCloner;
use App\Catalogue\RepositoryScanner;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Process\Process;

/**
 * The package-released dispatch's working copies: `app:catalog:clone` clones every repository the catalogue
 * lists (the tests' fixtures), at its branch, from repositories made here (--from) as the workflow clones
 * GitHub - and RepositoryScanner reads them as it reads a maintainer's working copies.
 */
final class CatalogCloneTest extends KernelTestCase
{
    private string $dir;
    /** @var array<string, string> repository => the head of the branch the catalogue names */
    private array $heads = [];

    protected function setUp(): void
    {
        $this->dir = sys_get_temp_dir().'/catalog-clone-test-'.bin2hex(random_bytes(4));
        self::bootKernel();
        $catalogue = self::getContainer()->get(Catalogue::class);
        foreach ($catalogue->all() as $package) {
            if (null !== $repository = $package->getRepository()) {
                $this->heads[$repository] = $this->repository($repository, (string) $package->getBranch(), $package->getName());
            }
        }
        // A package the catalogue does not list yet, on its default branch.
        $this->heads['glitchr-omnisense/omnitrade-newcomer'] = $this->repository('glitchr-omnisense/omnitrade-newcomer', 'main', 'omnitrade/newcomer');
    }

    protected function tearDown(): void
    {
        (new Filesystem())->remove($this->dir);
        parent::tearDown();
    }

    public function testEveryRepositoryOfTheCatalogueIsClonedAtItsBranch(): void
    {
        $tester = new CommandTester((new Application(self::$kernel))->find('app:catalog:clone'));
        $tester->execute(['dir' => $this->dir.'/repos', '--from' => 'file://'.$this->dir.'/remote', '--repository' => ['glitchr-omnisense/omnitrade-newcomer'], '--jobs' => '3']);
        $tester->assertCommandIsSuccessful();
        self::assertStringContainsString(\count($this->heads).' repositories cloned', $tester->getDisplay());

        $scanned = (new RepositoryScanner($this->dir.'/repos'))->scan();
        self::assertArrayHasKey('omnitrade/newcomer', $scanned);
        foreach ($scanned as $name => $package) {
            self::assertNotNull($package->repository, $name);
            self::assertSame($this->heads[$package->repository], $package->head, $name.': the published head of its branch');
        }
        self::assertSame('main', $scanned['omnibase/blog']->branch);
        self::assertSame('4.x', $scanned['omnisense/omnibase']->branch);
        self::assertCount(\count($this->heads), $scanned);
    }

    public function testARepositoryThatDoesNotCloneFailsTheWholeCall(): void
    {
        (new Filesystem())->remove($this->dir.'/remote/glitchr-omnisense/omnibase-blog.git');
        $tester = new CommandTester((new Application(self::$kernel))->find('app:catalog:clone'));

        self::assertSame(1, $tester->execute(['dir' => $this->dir.'/repos', '--from' => 'file://'.$this->dir.'/remote']));
        self::assertStringContainsString('glitchr-omnisense/omnibase-blog', $tester->getDisplay());
    }

    public function testOnlyAnOwnerTheCatalogueHasIsCloned(): void
    {
        $tester = new CommandTester((new Application(self::$kernel))->find('app:catalog:clone'));

        self::assertSame(1, $tester->execute(['dir' => $this->dir.'/repos', '--from' => 'file://'.$this->dir.'/remote', '--repository' => ['someone-else/omnitrade-x']]));
        self::assertSame(1, $tester->execute(['dir' => $this->dir.'/other', '--from' => 'file://'.$this->dir.'/remote', '--repository' => ['../escape']]));
        self::assertDirectoryDoesNotExist($this->dir.'/repos/'.RepositoryCloner::FOLDER);
    }

    /** A repository at <dir>/remote/<owner>/<name>.git whose $branch holds a composer.json (or package.json): its head. */
    private function repository(string $repository, string $branch, string $name): string
    {
        $work = $this->dir.'/work/'.$repository;
        (new Filesystem())->mkdir($work);
        $manifest = str_starts_with($name, '@') ? 'package.json' : 'composer.json';
        file_put_contents($work.'/'.$manifest, json_encode(['name' => $name, 'support' => ['source' => 'https://github.com/'.$repository]], \JSON_UNESCAPED_SLASHES)."\n");
        $git = static function (string ...$arguments) use ($work): string {
            $process = new Process(['git', '-c', 'user.name=Test', '-c', 'user.email=test@example.org', '-C', $work, ...$arguments]);
            $process->mustRun();

            return trim($process->getOutput());
        };
        $git('init', '--quiet');
        $git('symbolic-ref', 'HEAD', 'refs/heads/'.$branch);
        $git('add', $manifest);
        $git('commit', '--quiet', '-m', 'first');
        $head = $git('rev-parse', 'HEAD');
        $bare = $this->dir.'/remote/'.$repository.'.git';
        (new Process(['git', 'clone', '--quiet', '--bare', $work, $bare]))->mustRun();
        // The bare copy's HEAD names the branch: a clone without --branch takes it.
        (new Process(['git', '-C', $bare, 'symbolic-ref', 'HEAD', 'refs/heads/'.$branch]))->mustRun();

        return $head;
    }
}
