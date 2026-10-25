<?php

namespace App\Content;

use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Process\Process;

/**
 * Brings each manual's docs/ folder, at the ref config/documentation.yaml
 * pins, into var/docs/<key>/<ref>/, and writes var/docs/manifest.json: for
 * each series, the ref asked for and the commit it resolved to, and the
 * number of files. A ref already fetched is not fetched again (it cannot
 * change); a folder without its ref is removed.
 *
 * The source is GitHub (https://github.com/<repository>.git, one shallow
 * fetch of the commit), or, given $local, a working copy of the repository
 * under that folder that has the commit (no network: a maintainer's
 * machine, or a CI cache).
 */
class DocsFetcher
{
    /** Where a PHP package's own .php files and composer.json are kept beside its docs, at the same commit: its API reference's source. */
    public const SOURCES = '.php';

    private readonly Filesystem $fs;

    /** @var array<string, string>|null repository => working copy */
    private ?array $clones = null;

    public function __construct(
        private readonly Documentation $documentation,
        #[Autowire('%app.docs_dir%')] private readonly string $dir,
        #[Autowire('%env(default::DOCS_GIT_BASE)%')] private readonly ?string $gitBase = null,
    ) {
        $this->fs = new Filesystem();
    }

    /**
     * @param callable(string $manual, string $series, string $what): void|null $progress
     *
     * @return array{fetched: int, kept: int, failed: array<string, string>}
     */
    public function fetch(?string $local = null, ?callable $progress = null, ?string $only = null): array
    {
        $report = ['fetched' => 0, 'kept' => 0, 'failed' => []];
        $manifest = ['manuals' => []];
        $wanted = [];
        $this->fs->mkdir($this->dir);

        foreach ($this->documentation->manuals() as $manual) {
            if (null !== $only && $manual->key !== $only) {
                continue;
            }
            foreach ($manual->versions as $series => $source) {
                $target = $this->dir.'/'.$manual->key.'/'.$source['ref'];
                $wanted[$manual->key][$source['ref']] = true;
                $commitFile = $target.'/.commit';
                if (is_file($commitFile)) {
                    ++$report['kept'];
                    $progress && $progress($manual->key, $series, 'kept');
                } else {
                    try {
                        $this->fetchOne($manual, $source, $target, $local);
                        ++$report['fetched'];
                        $progress && $progress($manual->key, $series, 'fetched');
                    } catch (\RuntimeException $e) {
                        $report['failed'][$manual->key.' '.$series] = $e->getMessage();
                        $progress && $progress($manual->key, $series, 'failed: '.$e->getMessage());
                        continue;
                    }
                }
                if (!str_starts_with($manual->package, '@') && !is_file($target.'/'.self::SOURCES.'/.commit')) {
                    try {
                        $this->fetchSources($manual, $source, $target, $local);
                        $progress && $progress($manual->key, $series, 'sources fetched');
                    } catch (\RuntimeException $e) {
                        $report['failed'][$manual->key.' '.$series.' (sources)'] = $e->getMessage();
                        $progress && $progress($manual->key, $series, 'sources failed: '.$e->getMessage());
                    }
                }
                $files = 0;
                $skip = new \RecursiveCallbackFilterIterator(new \RecursiveDirectoryIterator($target, \FilesystemIterator::SKIP_DOTS), static fn (\SplFileInfo $f): bool => self::SOURCES !== $f->getFilename());
                foreach (new \RecursiveIteratorIterator($skip) as $file) {
                    $files += '.commit' !== $file->getFilename() ? 1 : 0;
                }
                $manifest['manuals'][$manual->key][$series] = [
                    'repository' => $manual->repository,
                    'ref' => $source['ref'],
                    'commit' => trim((string) file_get_contents($commitFile)),
                    'path' => $source['path'].'/',
                    'files' => $files,
                ];
            }
        }

        if (null === $only) {
            // Refs no longer pinned: their folders go.
            foreach (glob($this->dir.'/*/*', \GLOB_ONLYDIR) ?: [] as $folder) {
                if (!isset($wanted[basename(\dirname($folder))][basename($folder)])) {
                    $this->fs->remove($folder);
                }
            }
            ksort($manifest['manuals']);
            file_put_contents($this->dir.'/manifest.json', json_encode($manifest, \JSON_PRETTY_PRINT | \JSON_UNESCAPED_SLASHES).\PHP_EOL);
        }

        return $report;
    }

    /**
     * @param array{branch: ?string, ref: string, path: string, eol: bool} $source
     */
    private function fetchOne(Manual $manual, array $source, string $target, ?string $local): void
    {
        $work = $target.'.part';
        $this->fs->remove([$work, $target]);
        $this->fs->mkdir($work);
        $strip = '' === $source['path'] ? 0 : substr_count($source['path'], '/') + 1;

        $clone = null !== $local ? $this->localClone($local, $manual->repository, $source['ref']) : null;
        if (null === $clone) {
            $clone = $work.'/.git-fetch';
            $url = rtrim($this->gitBase ?: 'https://github.com', '/').'/'.$manual->repository.'.git';
            $this->run(['git', 'init', '--quiet', $clone]);
            $this->run(['git', '-C', $clone, 'fetch', '--quiet', '--depth=1', '--no-tags', $url, $source['ref']]);
            $ref = 'FETCH_HEAD';
        } else {
            $ref = $source['ref'];
        }
        $commit = trim($this->run(['git', '-c', 'safe.directory=*', '-C', $clone, 'rev-parse', '--verify', $ref.'^{commit}']));
        $archive = Process::fromShellCommandline(sprintf(
            'git -c safe.directory=* -C %s archive --format=tar %s -- %s | tar -x -C %s --strip-components=%d',
            escapeshellarg($clone), escapeshellarg($commit), escapeshellarg('' === $source['path'] ? '.' : $source['path']), escapeshellarg($work), $strip,
        ));
        $archive->setTimeout(300)->run();
        if (!$archive->isSuccessful()) {
            $this->fs->remove($work);
            throw new \RuntimeException(sprintf('%s has no %s/ at %s: %s', $manual->repository, $source['path'], $source['ref'], trim($archive->getErrorOutput())));
        }
        $this->fs->remove($work.'/.git-fetch');
        file_put_contents($work.'/.commit', $commit.\PHP_EOL);
        $this->fs->rename($work, $target);
    }

    /**
     * The package's .php files and its composer.json at the series' commit, into <target>/.php: what
     * its API reference is read from (omnidocs/php reads them, never loads them).
     *
     * @param array{branch: ?string, ref: string, path: string, eol: bool} $source
     */
    private function fetchSources(Manual $manual, array $source, string $target, ?string $local): void
    {
        $work = $target.'/'.self::SOURCES.'.part';
        $this->fs->remove([$work, $target.'/'.self::SOURCES]);
        $this->fs->mkdir($work);
        $clone = null !== $local ? $this->localClone($local, $manual->repository, $source['ref']) : null;
        if (null === $clone) {
            $clone = $work.'/.git-fetch';
            $url = rtrim($this->gitBase ?: 'https://github.com', '/').'/'.$manual->repository.'.git';
            $this->run(['git', 'init', '--quiet', $clone]);
            $this->run(['git', '-C', $clone, 'fetch', '--quiet', '--depth=1', '--no-tags', $url, $source['ref']]);
            $ref = 'FETCH_HEAD';
        } else {
            $ref = $source['ref'];
        }
        $commit = trim($this->run(['git', '-c', 'safe.directory=*', '-C', $clone, 'rev-parse', '--verify', $ref.'^{commit}']));
        $archive = Process::fromShellCommandline(sprintf(
            'git -c safe.directory=* -C %s archive --format=tar %s -- %s %s | tar -x -C %s',
            escapeshellarg($clone), escapeshellarg($commit), escapeshellarg(':(glob)**/*.php'), escapeshellarg(':(glob)composer.json'), escapeshellarg($work),
        ));
        $archive->setTimeout(300)->run();
        if (!$archive->isSuccessful()) {
            $this->fs->remove($work);
            throw new \RuntimeException(sprintf('%s: no PHP files at %s: %s', $manual->repository, $source['ref'], trim($archive->getErrorOutput())));
        }
        $this->fs->remove($work.'/.git-fetch');
        file_put_contents($work.'/.commit', $commit.\PHP_EOL);
        $this->fs->rename($work, $target.'/'.self::SOURCES);
    }

    /** A working copy of $repository under $root that has $ref. */
    private function localClone(string $root, string $repository, string $ref): ?string
    {
        if (null === $this->clones) {
            $this->clones = [];
            $dirs = array_merge(glob(rtrim($root, '/').'/*', \GLOB_ONLYDIR) ?: [], glob(rtrim($root, '/').'/*/*', \GLOB_ONLYDIR) ?: []);
            foreach ($dirs as $dir) {
                if (!is_dir($dir.'/.git')) {
                    continue;
                }
                $process = new Process(['git', '-c', 'safe.directory=*', '-C', $dir, 'remote', 'get-url', 'origin']);
                $process->run();
                if (preg_match('#github\.com[:/]([\w.-]+/[\w.-]+?)(?:\.git)?/?$#', trim($process->getOutput()), $m)) {
                    $this->clones[strtolower($m[1])] ??= $dir;
                }
            }
        }
        $dir = $this->clones[strtolower($repository)] ?? null;
        if (null === $dir) {
            return null;
        }
        $has = new Process(['git', '-c', 'safe.directory=*', '-C', $dir, 'cat-file', '-e', $ref.'^{commit}']);
        $has->run();

        return $has->isSuccessful() ? $dir : null;
    }

    /** @param list<string> $command */
    private function run(array $command): string
    {
        $process = new Process($command);
        $process->setTimeout(300)->run();
        if (!$process->isSuccessful()) {
            throw new \RuntimeException(trim($process->getErrorOutput()) ?: 'git failed');
        }

        return $process->getOutput();
    }
}
