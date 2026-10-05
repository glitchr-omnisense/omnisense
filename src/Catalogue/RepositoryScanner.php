<?php

namespace App\Catalogue;

use Base\Wikidoc\Manual\Git;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Reads the packages where they are written: every repository mounted
 * under the repositories' folder (`<family>/<repository>`, or a library's
 * own folder) that holds a composer.json or a package.json whose name is
 * one of the ecosystem's (Tier::of()). Reads only: the mount is read-only.
 *
 * And the sites: the composer.json and package.json of each site mounted
 * under the sites' folder, for "used by".
 */
class RepositoryScanner
{
    public function __construct(
        #[Autowire('%app.catalogue.repos_dir%')] private readonly string $reposDir,
        #[Autowire('%app.catalogue.sites_dir%')] private readonly string $sitesDir,
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
        $packages = [];
        foreach (array_merge(glob($root.'/*', \GLOB_ONLYDIR) ?: [], glob($root.'/*/*', \GLOB_ONLYDIR) ?: []) as $dir) {
            $package = $this->read($dir, $root);
            if (null !== $package && !isset($packages[$package->name])) {
                $packages[$package->name] = $package;
            }
        }
        ksort($packages);

        return $packages;
    }

    /**
     * What each site requires: its folder's name => the names in its
     * composer.json and package.json (require, dependencies).
     *
     * @return array<string, list<string>>
     */
    public function sites(): array
    {
        $sites = [];
        foreach (glob(rtrim($this->sitesDir, '/').'/*', \GLOB_ONLYDIR) ?: [] as $dir) {
            $names = [];
            foreach (['composer.json' => ['require'], 'package.json' => ['dependencies', 'devDependencies']] as $file => $keys) {
                $json = self::json($dir.'/'.$file);
                foreach ($keys as $key) {
                    $names = array_merge($names, array_keys((array) ($json[$key] ?? [])));
                }
            }
            if ([] !== $names) {
                $sites[basename($dir)] = array_values(array_unique(array_map('strval', $names)));
            }
        }
        ksort($sites);

        return $sites;
    }

    private function read(string $dir, string $root): ?ScannedPackage
    {
        $json = self::json($dir.'/composer.json') ?: self::json($dir.'/package.json');
        $name = \is_string($json['name'] ?? null) ? $json['name'] : null;
        if (null === $name || null === $floor = Tier::of($name)) {
            return null;
        }
        [$tier, $family] = $floor;

        $license = $json['license'] ?? null;
        $readme = is_file($dir.'/README.md') ? (string) file_get_contents($dir.'/README.md') : null;

        return new ScannedPackage(
            name: $name,
            tier: $tier,
            family: $family,
            description: \is_string($json['description'] ?? null) ? $json['description'] : null,
            license: \is_array($license) ? implode(' or ', $license) : (\is_string($license) ? $license : null),
            keywords: array_values(array_filter((array) ($json['keywords'] ?? []), 'is_string')),
            requires: array_map('strval', array_keys(array_merge((array) ($json['require'] ?? []), (array) ($json['dependencies'] ?? []), (array) ($json['peerDependencies'] ?? [])))),
            path: ltrim(substr($dir, \strlen($root)), '/'),
            branch: Git::branch($dir),
            repositoryUrl: Git::origin($dir) ?? self::repositoryOf($json),
            readme: $readme,
            docPages: self::countPages($dir.'/docs'),
        );
    }

    /** @param array<string, mixed> $json */
    private static function repositoryOf(array $json): ?string
    {
        $url = $json['support']['source'] ?? (\is_array($json['repository'] ?? null) ? ($json['repository']['url'] ?? null) : ($json['repository'] ?? null));

        return \is_string($url) ? Git::webUrl((string) preg_replace('#^git\+#', '', $url)) : null;
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
