<?php

namespace App\Site;

use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Process\Process;

/**
 * The deployment manifest published with the site (provenance.json, master
 * plan section 10, item 10): the site's commit and the files the build
 * changed in its checkout, the commit each package's documentation was read
 * at, the packages and tools that rendered it, and the export's checksums.
 *
 * Deterministic: no time, no run, nothing a build has of its own - two
 * builds from the same sources write the same file, byte for byte (what
 * asked for a build and its counts go to the run's summary instead). Written
 * after the export was checked and compared with a full one; not one of the
 * files omnibridge-static.json lists, whose checksum it holds. The exporter's
 * report has no room for data of the site's own, hence a file beside it.
 */
class Provenance
{
    public const FILE = 'provenance.json';

    public function __construct(
        #[Autowire('%kernel.project_dir%')] private readonly string $projectDir,
        #[Autowire('%app.docs_dir%')] private readonly string $docsDir,
        #[Autowire('%app.site.repository%')] private readonly string $repository,
    ) {
    }

    /**
     * @param array<string, string|null> $environment the CI's variables (GITHUB_SHA, GITHUB_REF, GITHUB_REPOSITORY)
     *
     * @return array<string, mixed> what was written to <folder>/provenance.json
     */
    public function write(string $folder, array $environment = []): array
    {
        $folder = rtrim($folder, '/');
        $report = $folder.'/omnibridge-static.json';
        if (!is_file($report)) {
            throw new \InvalidArgumentException(\sprintf('"%s" holds no export (no omnibridge-static.json).', $folder));
        }
        $env = static fn (string $name): ?string => isset($environment[$name]) && '' !== $environment[$name] ? (string) $environment[$name] : null;

        $commit = $env('GITHUB_SHA') ?? $this->git(['rev-parse', 'HEAD']);
        $packages = $this->packages();

        $data = [
            'about' => 'The deployment manifest: what this site was built from - the site\'s commit and the files the build changed in '
                .'its checkout (the data it fetched: compatibility, stars, policies, a moved documentation pin), each documentation '
                .'source\'s commit, the packages and tools, the checksums. No time nor run: two builds from the same sources write '
                .'the same file. The export (omnibridge-static.json and the files it lists) is compared with a full export before '
                .'this file is written; this file is not one of them.',
            'site' => [
                'repository' => $env('GITHUB_REPOSITORY') ?? $this->repository,
                'commit' => $commit,
                'ref' => $env('GITHUB_REF'),
                // The commit's own date: a time every build of the commit agrees on.
                'committed' => null !== $commit ? $this->git(['show', '-s', '--format=%cI', $commit]) : null,
                'modified' => (object) $this->modified(),
            ],
            'tools' => [
                'php' => \PHP_VERSION,
                'icu' => \defined('INTL_ICU_VERSION') ? \INTL_ICU_VERSION : null,
                'composer' => $this->version(['composer', '--version', '--no-ansi'], '/Composer version (\S+)/'),
                'node' => $this->version(['node', '--version'], '/^v?(\S+)/'),
                'yarn' => $this->version(['yarn', '--version'], '/^(\S+)/'),
                'omnibridge/static' => $packages['omnibridge/static']['version'] ?? null,
            ],
            'packages' => $packages,
            'documentation' => $this->documentation(),
            'artifact' => $this->artifact($folder),
        ];

        file_put_contents($folder.'/'.self::FILE, json_encode($data, \JSON_PRETTY_PRINT | \JSON_UNESCAPED_SLASHES | \JSON_UNESCAPED_UNICODE | \JSON_THROW_ON_ERROR).\PHP_EOL);

        return $data;
    }

    /**
     * @return array<string, string|null> each tracked file the checkout changed, with its SHA-256 (null: deleted) - what
     *                                    the build fetched before the export (config/compatibility.yaml, config/github.yaml,
     *                                    config/policies.json) or a dispatch moved (config/documentation.yaml)
     */
    private function modified(): array
    {
        $out = [];
        foreach (explode("\n", (string) $this->git(['diff', '--name-only', '--no-renames', 'HEAD'])) as $path) {
            if ('' !== $path) {
                $file = $this->projectDir.'/'.$path;
                $out[$path] = is_file($file) ? (string) hash_file('sha256', $file) : null;
            }
        }
        ksort($out, \SORT_STRING);

        return $out;
    }

    /** @return array<string, array{version: string, reference: ?string}> the packages the site runs on (composer.lock, without require-dev) */
    private function packages(): array
    {
        $lock = json_decode((string) @file_get_contents($this->projectDir.'/composer.lock'), true);
        $packages = [];
        foreach ((array) ($lock['packages'] ?? []) as $package) {
            if (\is_array($package) && \is_string($package['name'] ?? null)) {
                $packages[$package['name']] = [
                    'version' => (string) ($package['version'] ?? ''),
                    'reference' => $package['source']['reference'] ?? $package['dist']['reference'] ?? null,
                ];
            }
        }
        ksort($packages);

        return $packages;
    }

    /** @return array<string, array<string, array{repository: ?string, ref: ?string, commit: ?string}>> each manual's series, at the commit app:docs:fetch resolved */
    private function documentation(): array
    {
        $manifest = json_decode((string) @file_get_contents(rtrim($this->docsDir, '/').'/manifest.json'), true);
        $out = [];
        foreach ((array) ($manifest['manuals'] ?? []) as $key => $series) {
            foreach ((array) $series as $name => $source) {
                $out[(string) $key][(string) $name] = [
                    'repository' => $source['repository'] ?? null,
                    'ref' => $source['ref'] ?? null,
                    'commit' => $source['commit'] ?? null,
                ];
            }
        }
        ksort($out);

        return $out;
    }

    /** @return array{report: string, files: int, bytes: int, sha256: string} the report's checksum, and the whole folder's (each file's path and SHA-256, sorted) */
    private function artifact(string $folder): array
    {
        $lines = [];
        $bytes = 0;
        foreach (new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($folder, \FilesystemIterator::SKIP_DOTS)) as $file) {
            if (!$file instanceof \SplFileInfo || !$file->isFile()) {
                continue;
            }
            $path = substr($file->getPathname(), \strlen($folder) + 1);
            if (self::FILE !== $path) {
                $lines[] = $path.' '.hash_file('sha256', $file->getPathname());
                $bytes += (int) $file->getSize();
            }
        }
        sort($lines, \SORT_STRING);

        return [
            'report' => (string) hash_file('sha256', $folder.'/omnibridge-static.json'),
            'files' => \count($lines),
            'bytes' => $bytes,
            'sha256' => hash('sha256', implode("\n", $lines)),
        ];
    }

    /** @param list<string> $arguments */
    private function git(array $arguments): ?string
    {
        $process = new Process(['git', '-c', 'safe.directory=*', '-C', $this->projectDir, ...$arguments]);
        $process->setTimeout(30)->run();

        return $process->isSuccessful() ? (trim($process->getOutput()) ?: null) : null;
    }

    /** @param list<string> $command */
    private function version(array $command, string $pattern): ?string
    {
        try {
            $process = new Process($command, $this->projectDir);
            $process->setTimeout(30)->run();
        } catch (\Throwable) {
            return null;
        }

        return $process->isSuccessful() && 1 === preg_match($pattern, trim($process->getOutput()), $m) ? $m[1] : null;
    }
}
