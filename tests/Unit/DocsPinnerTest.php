<?php

namespace App\Tests\Unit;

use App\Content\DocsPinner;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Process\Process;
use Symfony\Component\Yaml\Yaml;

/**
 * A documentation package's dispatch moves its series to a commit of its branch, and nothing else: read
 * against a repository made here (DOCS_GIT_BASE), the way the workflow reads GitHub.
 */
final class DocsPinnerTest extends TestCase
{
    private const YAML = <<<'YAML'
        # A comment the pin keeps.
        packages:
          widget:
            package: acme/widget
            repository: acme/widget
            default: '1.x'
            versions:
              '1.x':
                branch: 1.x
                ref: %FIRST%
                pinned: branch-head
                path: docs/
              '0.9':
                branch: '0.9'
                ref: 2222222222222222222222222222222222222222
                path: docs/
                eol: true
          other:
            package: acme/other
            repository: acme/other
            default: main
            versions:
              main:
                branch: main
                ref: %FIRST%
                pinned: branch-head
                path: docs/

        YAML;

    private string $dir;
    private string $file;
    /** @var array{first: string, second: string, elsewhere: string} */
    private array $commits;

    protected function setUp(): void
    {
        $this->dir = sys_get_temp_dir().'/docs-pin-test-'.bin2hex(random_bytes(4));
        $repo = $this->dir.'/git/acme/widget.git';
        (new Filesystem())->mkdir($repo);
        $git = function (string ...$arguments) use ($repo): string {
            $process = new Process(['git', '-c', 'user.name=Test', '-c', 'user.email=test@example.org', '-c', 'init.defaultBranch=1.x', '-C', $repo, ...$arguments]);
            $process->mustRun();

            return trim($process->getOutput());
        };
        $git('init', '--quiet');
        $git('commit', '--quiet', '--allow-empty', '-m', 'first');
        $first = $git('rev-parse', 'HEAD');
        $git('commit', '--quiet', '--allow-empty', '-m', 'second');
        $second = $git('rev-parse', 'HEAD');
        $git('checkout', '--quiet', '-b', 'elsewhere', $first);
        $git('commit', '--quiet', '--allow-empty', '-m', 'not on 1.x');
        $elsewhere = $git('rev-parse', 'HEAD');
        $this->commits = ['first' => $first, 'second' => $second, 'elsewhere' => $elsewhere];

        $this->file = $this->dir.'/documentation.yaml';
        file_put_contents($this->file, str_replace('%FIRST%', $first, self::YAML));
    }

    protected function tearDown(): void
    {
        (new Filesystem())->remove($this->dir);
    }

    private function pinner(): DocsPinner
    {
        return new DocsPinner($this->file, 'file://'.$this->dir.'/git');
    }

    public function testACommitOfTheBranchMovesThatSeriesAndNothingElse(): void
    {
        $before = (string) file_get_contents($this->file);
        $pin = $this->pinner()->pin('widget', $this->commits['second']);

        self::assertSame(['key' => 'widget', 'series' => '1.x', 'from' => $this->commits['first'], 'to' => $this->commits['second'], 'moved' => true], $pin);
        $after = (string) file_get_contents($this->file);
        // One line changed: the series' ref. The comment, the older series and the other manual are as they were.
        self::assertSame(str_replace("        ref: {$this->commits['first']}\n        pinned: branch-head\n        path: docs/\n      '0.9'", "        ref: {$this->commits['second']}\n        pinned: branch-head\n        path: docs/\n      '0.9'", $before), $after);
        self::assertStringStartsWith('# A comment the pin keeps.', $after);
        self::assertSame($this->commits['first'], Yaml::parse($after)['packages']['other']['versions']['main']['ref']);
    }

    public function testWithoutACommitTheBranchHeadIsTaken(): void
    {
        $pin = $this->pinner()->pin('widget');

        self::assertSame($this->commits['second'], $pin['to']);
        self::assertSame($this->commits['second'], Yaml::parseFile($this->file)['packages']['widget']['versions']['1.x']['ref']);
    }

    public function testTheCommitAlreadyPinnedChangesNothing(): void
    {
        $before = (string) file_get_contents($this->file);

        self::assertFalse($this->pinner()->pin('widget', $this->commits['first'])['moved']);
        self::assertSame($before, file_get_contents($this->file));
    }

    public function testACommitOffTheBranchIsRefused(): void
    {
        $before = (string) file_get_contents($this->file);
        try {
            $this->pinner()->pin('widget', $this->commits['elsewhere']);
            self::fail('A commit that is not on 1.x was pinned.');
        } catch (\RuntimeException $e) {
            self::assertStringContainsString('is not on acme/widget\'s branch 1.x', $e->getMessage());
        }
        self::assertSame($before, file_get_contents($this->file));
    }

    public function testAnUnknownManualOrSeriesIsTold(): void
    {
        foreach ([['nothing', null], ['widget', '2.x']] as [$key, $series]) {
            try {
                $this->pinner()->pin($key, null, $series);
                self::fail(\sprintf('%s %s was pinned.', $key, $series ?? 'default'));
            } catch (\InvalidArgumentException) {
                $this->addToAssertionCount(1);
            }
        }
    }

    public function testOnlyAFullCommitShaIsTaken(): void
    {
        $this->expectException(\UnexpectedValueException::class);
        $this->pinner()->pin('widget', 'main');
    }
}
