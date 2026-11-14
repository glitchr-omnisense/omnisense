<?php

namespace App\Catalogue;

use Symfony\Component\Process\Process;

/**
 * The working copies app:catalog:import reads (RepositoryScanner), made where
 * there are none - the website workflow's runner: a shallow clone of each
 * repository the catalogue lists, at the branch it names, plus the ones a
 * release asks for (a package not in the catalogue yet, on its default
 * branch), under <dir>/omnirepos/<owner>--<name>, the layout the scanner
 * walks (<dir>/omni*\/*).
 *
 * All or nothing: a repository that did not clone fails the whole call, since
 * an import over a partial set would drop the missing packages from the
 * catalogue.
 */
class RepositoryCloner
{
    public const FOLDER = 'omnirepos';

    private const NAME = '/^[A-Za-z0-9][A-Za-z0-9_.-]*\/[A-Za-z0-9][A-Za-z0-9_.-]*$/';

    public function __construct(private readonly Catalogue $catalogue)
    {
    }

    /**
     * @param list<string> $extra repositories (owner/name) to clone beside the catalogue's, on their default branch;
     *                            only of an owner the catalogue already has
     *
     * @return array<string, ?string> each repository cloned => its branch (null: the default one)
     */
    public function cloneInto(string $dir, array $extra = [], string $from = 'https://github.com', int $jobs = 8): array
    {
        $wanted = [];
        foreach ($this->catalogue->all() as $package) {
            $repository = $package->getRepository();
            if (null !== $repository && 1 === preg_match(self::NAME, $repository)) {
                $wanted[$repository] ??= $package->getBranch();
            }
        }
        $owners = array_unique(array_map(static fn (string $r): string => strtolower(explode('/', $r)[0]), array_keys($wanted)));
        foreach ($extra as $repository) {
            if (1 !== preg_match(self::NAME, $repository) || !\in_array(strtolower(explode('/', $repository)[0]), $owners, true)) {
                throw new \InvalidArgumentException(\sprintf('"%s" is not a repository (owner/name) of an owner the catalogue has (%s).', $repository, implode(', ', $owners)));
            }
            $wanted[$repository] ??= null;
        }
        ksort($wanted, \SORT_STRING);

        $root = rtrim($dir, '/').'/'.self::FOLDER;
        if (is_dir($root)) {
            throw new \InvalidArgumentException(\sprintf('"%s" exists already: the clones go into a new folder.', $root));
        }
        if (!mkdir($root, 0o775, true)) {
            throw new \RuntimeException(\sprintf('"%s" could not be made.', $root));
        }

        $queue = $wanted;
        $running = [];
        $failed = [];
        while ([] !== $queue || [] !== $running) {
            while ([] !== $queue && \count($running) < max(1, $jobs)) {
                $repository = array_key_first($queue);
                $branch = $queue[$repository];
                unset($queue[$repository]);
                $target = $root.'/'.str_replace('/', '--', $repository);
                $process = new Process(['git', 'clone', '--quiet', '--depth', '1', '--no-tags', ...(null !== $branch ? ['--branch', $branch] : []), rtrim($from, '/').'/'.$repository.'.git', $target]);
                $process->setTimeout(300)->start();
                $running[$repository] = $process;
            }
            foreach ($running as $repository => $process) {
                if (!$process->isRunning()) {
                    if (!$process->isSuccessful()) {
                        $failed[] = $repository.': '.(trim($process->getErrorOutput()) ?: 'git clone failed');
                    }
                    unset($running[$repository]);
                }
            }
            usleep(20_000);
        }
        if ([] !== $failed) {
            sort($failed);
            throw new \RuntimeException(\sprintf("%d of %d repositories did not clone:\n%s", \count($failed), \count($wanted), implode("\n", $failed)));
        }

        return $wanted;
    }
}
