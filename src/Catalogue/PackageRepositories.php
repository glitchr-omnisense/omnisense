<?php

namespace App\Catalogue;

use App\Entity\Package;
use App\Repository\PackageRepository;
use Git\Repository\RepositoryProviderInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * The catalogue's packages as git/git-bundle repositories: each package's
 * working copy, read in place through libgit2. No url is given, so the
 * bundle's warmer never clones nor fetches - the mounts are read only, and
 * nothing here asks anything of the network.
 */
final class PackageRepositories implements RepositoryProviderInterface
{
    public function __construct(
        private readonly PackageRepository $packages,
        /** Where the repositories are mounted: a package's path is kept relative to it. */
        #[Autowire('%app.catalogue.repos_dir%')] private readonly string $reposDir,
    ) {
    }

    /** A package's folder on disk: its path, kept relative to the repositories' folder, made absolute. */
    public function directoryOf(Package $package): string
    {
        $path = $package->getPath();

        return str_starts_with($path, '/') ? rtrim($path, '/') : rtrim($this->reposDir, '/').'/'.trim($path, '/');
    }

    /** The name a package's repository goes by in git/git-bundle. */
    public static function nameOf(Package $package): string
    {
        return 'package.'.str_replace(['@', '/'], ['', '.'], $package->getHandle());
    }

    /** The working copy holding a package: its own directory, or the nearest one above it with a .git. */
    public function rootOf(Package $package): ?string
    {
        $path = $this->directoryOf($package);
        for ($i = 0; $i < 3 && '' !== $path && '/' !== $path; ++$i) {
            if (file_exists($path.'/.git')) {
                return $path;
            }
            $path = \dirname($path);
        }

        return null;
    }

    public function getRepositories(): array
    {
        $repositories = [];
        foreach ($this->packages->findCatalogue() as $package) {
            $root = $this->rootOf($package);
            if (null === $root) {
                continue;
            }
            $repositories[self::nameOf($package)] = [
                'path' => $root,
                'label' => $package->getName(),
                'description' => $package->getDescription(),
                'default_branch' => $package->getBranch() ?? 'HEAD',
            ];
        }

        return $repositories;
    }
}
