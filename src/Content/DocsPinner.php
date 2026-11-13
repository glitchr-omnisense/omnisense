<?php

namespace App\Content;

use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Process\Process;
use Symfony\Component\Yaml\Yaml;

/**
 * Moves one series of one manual in config/documentation.yaml to a commit
 * of the branch it follows: what a documentation package's dispatch asks
 * the website's workflow for (a new commit published on its branch). The
 * commit must be on that branch of the repository the manual names (fetched
 * from GitHub, or DOCS_GIT_BASE), never anything else; without a commit,
 * the branch's head. Only that series' `ref` line changes (and `pinned`, to
 * branch-head): the rest of the file, its comments and the other pins are
 * left as they are, and the file is read back to prove it.
 */
class DocsPinner
{
    public function __construct(
        #[Autowire('%app.documentation_file%')] private readonly string $file,
        #[Autowire('%env(default::DOCS_GIT_BASE)%')] private readonly ?string $gitBase = null,
    ) {
    }

    /**
     * @return array{key: string, series: string, from: string, to: string, moved: bool}
     *
     * @throws \InvalidArgumentException an unknown manual or series
     * @throws \RuntimeException         a malformed commit, a series without a branch, a commit that is not on the
     *                                   branch, a fetch that failed
     */
    public function pin(string $key, ?string $commit = null, ?string $series = null): array
    {
        $entry = $this->entries()[$key] ?? throw new \InvalidArgumentException(\sprintf('config/documentation.yaml has no manual "%s".', $key));
        $series ??= (string) ($entry['default'] ?? array_key_first((array) ($entry['versions'] ?? [])));
        $source = $entry['versions'][$series] ?? throw new \InvalidArgumentException(\sprintf('The manual "%s" has no series "%s".', $key, $series));
        $branch = isset($source['branch']) ? (string) $source['branch'] : throw new \UnexpectedValueException(\sprintf('%s %s follows no branch: a series kept for history is moved by hand.', $key, $series));
        if (null !== $commit && 1 !== preg_match('/^[0-9a-f]{40}$/', $commit)) {
            throw new \UnexpectedValueException(\sprintf('"%s" is not a full commit SHA (40 hexadecimal characters).', $commit));
        }
        $from = (string) $source['ref'];

        $commit = $this->onBranch((string) $entry['repository'], $branch, $commit);
        if ($commit === $from) {
            return ['key' => $key, 'series' => $series, 'from' => $from, 'to' => $commit, 'moved' => false];
        }

        $before = (string) file_get_contents($this->file);
        $after = $this->rewrite($before, $key, $series, $commit);
        // Read back: the same data but this one ref (and its `pinned`), or nothing is written.
        $expected = Yaml::parse($before);
        $expected['packages'][$key]['versions'][$series]['ref'] = $commit;
        if (isset($expected['packages'][$key]['versions'][$series]['pinned'])) {
            $expected['packages'][$key]['versions'][$series]['pinned'] = 'branch-head';
        }
        if (Yaml::parse($after) !== $expected) {
            throw new \RuntimeException(\sprintf('config/documentation.yaml: %s %s could not be moved line by line; the file is left as it was.', $key, $series));
        }
        file_put_contents($this->file, $after);

        return ['key' => $key, 'series' => $series, 'from' => $from, 'to' => $commit, 'moved' => true];
    }

    /** @return array<string, mixed> */
    private function entries(): array
    {
        return is_file($this->file) ? (array) (Yaml::parseFile($this->file)['packages'] ?? []) : [];
    }

    /** The commit, checked to be on the branch (an ancestor of its head); without one, the branch's head. */
    private function onBranch(string $repository, string $branch, ?string $commit): string
    {
        $fs = new Filesystem();
        $clone = $fs->tempnam(sys_get_temp_dir(), 'docs-pin-');
        $fs->remove($clone);
        try {
            $url = rtrim($this->gitBase ?: 'https://github.com', '/').'/'.$repository.'.git';
            $this->git(['init', '--quiet', '--bare', $clone]);
            // The branch's history without its files: enough to tell whether the commit is on it.
            $this->git(['-C', $clone, 'fetch', '--quiet', '--no-tags', '--filter=blob:none', $url, 'refs/heads/'.$branch]);
            $head = trim($this->git(['-C', $clone, 'rev-parse', '--verify', 'FETCH_HEAD^{commit}']));
            if (null === $commit) {
                return $head;
            }
            $ancestor = new Process(['git', '-C', $clone, 'merge-base', '--is-ancestor', $commit, $head]);
            $ancestor->setTimeout(300)->run();
            if (!$ancestor->isSuccessful()) {
                throw new \RuntimeException(\sprintf('%s is not on %s\'s branch %s (head %s).', $commit, $repository, $branch, $head));
            }

            return $commit;
        } finally {
            $fs->remove($clone);
        }
    }

    /** The file with the series' ref (and its `pinned`) replaced, every other line as it was. */
    private function rewrite(string $yaml, string $key, string $series, string $commit): string
    {
        $name = static fn (string $n): string => '(?:'.preg_quote($n, '/').'|\''.preg_quote($n, '/').'\'|"'.preg_quote($n, '/').'")';
        $lines = explode("\n", $yaml);
        $in = null; // 'key' inside the manual, 'series' inside the series
        $done = false;
        foreach ($lines as $i => $line) {
            if (1 === preg_match('/^  '.$name($key).':\s*$/', $line)) {
                $in = 'key';
            } elseif (null !== $in && 1 === preg_match('/^ {0,2}\S/', $line)) {
                $in = null; // the next manual
            } elseif (null !== $in && 1 === preg_match('/^      '.$name($series).':\s*$/', $line)) {
                $in = 'series';
            } elseif ('series' === $in && 1 === preg_match('/^ {0,6}\S/', $line)) {
                $in = 'key'; // the next series, or the manual's next field
            } elseif ('series' === $in && 1 === preg_match('/^        ref:\s/', $line)) {
                $lines[$i] = '        ref: '.$commit;
                $done = true;
            } elseif ('series' === $in && 1 === preg_match('/^        pinned:\s/', $line)) {
                $lines[$i] = '        pinned: branch-head';
            }
        }
        if (!$done) {
            throw new \RuntimeException(\sprintf('config/documentation.yaml: no ref line for %s %s.', $key, $series));
        }

        return implode("\n", $lines);
    }

    /** @param list<string> $arguments */
    private function git(array $arguments): string
    {
        $process = new Process(['git', '-c', 'safe.directory=*', ...$arguments]);
        $process->setTimeout(300)->run();
        if (!$process->isSuccessful()) {
            throw new \RuntimeException(trim($process->getErrorOutput()) ?: 'git failed');
        }

        return $process->getOutput();
    }
}
