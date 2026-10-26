<?php

namespace App\Catalogue;

use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Process\Process;

/**
 * Reads the packages where they are written: the working copies of the
 * ecosystem mounted under one folder (OMNI_REPOS: the family folders
 * `omni<domain>/<repository>`, and the JavaScript libraries' own folders),
 * each holding a composer.json or a package.json whose name is one of the
 * ecosystem's (Tier::of()). Reads only: the mount may be read-only.
 *
 * Used by `app:catalog:import`, a maintainer's tool: the website itself
 * never reads a repository, it reads catalog/*.yaml and the documentation
 * fetched at the commits config/documentation.yaml pins.
 */
class RepositoryScanner
{
    /** The JavaScript libraries of the house, each in a folder of its own. */
    public const LIBRARIES = ['flowjs', 'graphjs'];

    public function __construct(
        #[Autowire('%app.catalogue.repos_dir%')] private readonly string $reposDir,
    ) {
    }

    public function getReposDir(): string
    {
        return rtrim($this->reposDir, '/');
    }

    /** @return array<string, ScannedPackage> keyed by name, sorted */
    public function scan(): array
    {
        $root = $this->getReposDir();
        $dirs = [];
        foreach (glob($root.'/omni*', \GLOB_ONLYDIR) ?: [] as $family) {
            // The website's own folder is no package of the ecosystem (~/Sites/omniscient until
            // the folder takes the new name).
            if (\in_array(basename($family), ['omnisense', 'omniscient'], true)) {
                continue;
            }
            $dirs = array_merge($dirs, [$family], glob($family.'/*', \GLOB_ONLYDIR) ?: []);
        }
        foreach (self::LIBRARIES as $library) {
            $dirs[] = $root.'/'.$library;
        }
        sort($dirs);

        $packages = [];
        foreach ($dirs as $dir) {
            $package = is_dir($dir) ? $this->read($dir, $root) : null;
            if (null !== $package && !isset($packages[$package->name])) {
                $packages[$package->name] = $package;
            }
        }
        ksort($packages);

        return $packages;
    }

    private function read(string $dir, string $root): ?ScannedPackage
    {
        $json = self::json($dir.'/composer.json') ?: self::json($dir.'/package.json');
        $name = \is_string($json['name'] ?? null) ? $json['name'] : null;
        // A "project" is an application or a development workspace, not a package.
        if (null === $name || 'project' === ($json['type'] ?? null) || null === $floor = Tier::of($name)) {
            return null;
        }
        [$tier, $family] = $floor;

        $license = $json['license'] ?? null;
        $readme = is_file($dir.'/README.md') ? (string) file_get_contents($dir.'/README.md') : null;
        $branch = self::git($dir, 'branch', '--show-current');
        // A working copy on a local branch the remote does not have: the remote's default branch is the published one.
        if (null !== $branch && null === self::git($dir, 'rev-parse', '--verify', '--quiet', 'refs/remotes/origin/'.$branch)) {
            $default = self::git($dir, 'symbolic-ref', '--short', 'refs/remotes/origin/HEAD');
            $branch = null !== $default ? substr($default, \strlen('origin/')) : (null !== self::git($dir, 'rev-parse', '--verify', '--quiet', 'refs/remotes/origin/main') ? 'main' : $branch);
        }

        return new ScannedPackage(
            name: $name,
            tier: $tier,
            family: $family,
            description: \is_string($json['description'] ?? null) ? $json['description'] : null,
            license: \is_array($license) ? implode(' or ', $license) : (\is_string($license) ? $license : null),
            keywords: array_values(array_filter((array) ($json['keywords'] ?? []), 'is_string')),
            requires: array_map('strval', array_keys(array_merge((array) ($json['require'] ?? []), (array) ($json['dependencies'] ?? []), (array) ($json['peerDependencies'] ?? [])))),
            path: ltrim(substr($dir, \strlen($root)), '/'),
            branch: $branch,
            repository: self::repositoryOf(self::git($dir, 'remote', 'get-url', 'origin')) ?? self::repositoryOf(self::declaredSource($json)),
            readme: $readme,
            docPages: self::countPages($dir.'/docs'),
            // The published head: what the remote branch was last seen at, never an unpushed local commit.
            head: null !== $branch ? self::git($dir, 'rev-parse', '--verify', '--quiet', 'refs/remotes/origin/'.$branch) : null,
        );
    }

    /** "git@github.com:glitchr-omnisense/omnilab.git" → "glitchr-omnisense/omnilab"; null off GitHub. */
    public static function repositoryOf(?string $url): ?string
    {
        if (null === $url || 1 !== preg_match('#github\.com[:/]([\w.-]+/[\w.-]+?)(?:\.git)?/?$#', $url, $match)) {
            return null;
        }

        return $match[1];
    }

    /**
     * Where the package says its source is (composer.json's support.source, package.json's repository).
     *
     * @param array<string, mixed> $json
     */
    private static function declaredSource(array $json): ?string
    {
        $url = $json['support']['source'] ?? (\is_array($json['repository'] ?? null) ? ($json['repository']['url'] ?? null) : ($json['repository'] ?? null));

        return \is_string($url) ? $url : null;
    }

    private static function git(string $dir, string ...$args): ?string
    {
        if (!is_dir($dir.'/.git') && !is_file($dir.'/.git')) {
            return null;
        }
        $process = new Process(['git', '-c', 'safe.directory=*', '-C', $dir, ...$args]);
        $process->run();
        $out = trim($process->getOutput());

        return $process->isSuccessful() && '' !== $out ? $out : null;
    }

    private static function countPages(string $docs): int
    {
        if (!is_dir($docs)) {
            return 0;
        }
        $count = 0;
        foreach (new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($docs, \FilesystemIterator::SKIP_DOTS)) as $file) {
            $count += 'md' === strtolower($file->getExtension()) ? 1 : 0;
        }

        return $count;
    }

    /** @return array<string, mixed> */
    private static function json(string $file): array
    {
        if (!is_file($file)) {
            return [];
        }
        $json = json_decode((string) file_get_contents($file), true);

        return \is_array($json) ? $json : [];
    }
}
