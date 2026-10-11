<?php

namespace App\Catalogue;

use App\Entity\Package;
use Git\Service\Git2Service;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

/**
 * A package's versions, read in its repository through git/git-bundle
 * (libgit2, in process): the tags, the branches and the last commits of each.
 * A repository that cannot be read - none mounted, the extension missing -
 * gives an empty answer, and the registry's version stands in.
 */
class Versions
{
    /** @var array<string, PackageVersions> */
    private array $read = [];

    public function __construct(
        private readonly Git2Service $git,
        private readonly LoggerInterface $logger = new NullLogger(),
    ) {
    }

    public function of(Package $package, int $commits = 5): PackageVersions
    {
        $key = $package->getHandle().'#'.$commits;
        if (isset($this->read[$key])) {
            return $this->read[$key];
        }

        $name = PackageRepositories::nameOf($package);
        if (!\function_exists('git_repository_open') || !$this->git->hasRepository($name)) {
            return $this->read[$key] = new PackageVersions(publishedVersion: $package->getPublishedVersion());
        }

        try {
            $tags = [];
            foreach ($this->git->getTags($name) as $tag) {
                $version = PackageVersions::normalize($tag['name']);
                if (null === $version) {
                    continue;
                }
                $tags[] = [
                    'name' => $tag['name'],
                    'version' => $version,
                    'sha' => $tag['sha'],
                    'date' => $this->dateOf($name, $tag['sha']),
                    'stable' => PackageVersions::isStable($version),
                ];
            }
            usort($tags, static fn (array $a, array $b): int => version_compare($b['version'], $a['version']));

            $default = $package->getBranch();
            $branches = [];
            foreach ($this->git->getBranches($name) as $branch) {
                $log = $this->git->getCommitLog($name, $branch['sha'], $commits);
                $branches[] = [
                    'name' => $branch['name'],
                    'version' => PackageVersions::branchVersion($branch['name']),
                    'sha' => $branch['sha'],
                    'date' => isset($log[0]) ? $log[0]->committerDate : null,
                    'default' => null !== $default ? $branch['name'] === $default : $branch['is_head'],
                    'commits' => array_map(static fn ($commit): array => [
                        'sha' => $commit->shortSha,
                        'subject' => $commit->subject,
                        'date' => $commit->committerDate,
                    ], $log),
                ];
            }
            // The branch it is developed on first, then the others by their last commit.
            usort($branches, static fn (array $a, array $b): int => [$b['default'], $b['date']] <=> [$a['default'], $a['date']]);

            return $this->read[$key] = new PackageVersions($tags, $branches, true, $package->getPublishedVersion());
        } catch (\Throwable $e) {
            $this->logger->warning('The repository of {package} could not be read: {message}', ['package' => $package->getName(), 'message' => $e->getMessage()]);

            return $this->read[$key] = new PackageVersions(publishedVersion: $package->getPublishedVersion());
        }
    }

    private function dateOf(string $repository, string $sha): ?\DateTimeImmutable
    {
        try {
            return $this->git->getCommitLog($repository, $sha, 1)[0]->committerDate ?? null;
        } catch (\Throwable) {
            return null;
        }
    }
}
