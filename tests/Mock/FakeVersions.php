<?php

namespace App\Tests\Mock;

use App\Catalogue\PackageVersions;
use App\Catalogue\Versions;
use App\Entity\Package;
use Git\Service\Git2Service;

/**
 * What git/git-bundle would read in the fixtures' repositories, if they
 * were repositories: tests/Fixtures/repos holds working files, no .git. The
 * tags and branches below stand in, for the versions a package page shows
 * and the constraints the install selector writes. Reading a real
 * repository through libgit2 is checked in the browser, on the mounted
 * repositories.
 *
 *   glitchr/omnitrade   released: v1.4.2 (and a 2.0.0-beta.1), developed on 1.x and 2.x
 *   omnitrade/stripe    released: v1.4.0, one branch
 *   omnitrade/paypal    never released, developed on main
 *   glitchr/omnibus     never released, 2.x checked out, 1.x kept
 *   glitchr/omnibase    never released, 3.x
 *   omnibase/blog       never released, main
 *   omnibus/offline     no repository at all
 */
final class FakeVersions extends Versions
{
    private const REPOSITORIES = [
        'glitchr/omnitrade' => [
            'tags' => ['v1.4.2' => '2026-09-20', 'v1.4.1' => '2026-08-02', 'v1.3.0' => '2026-05-11', 'v2.0.0-beta.1' => '2026-10-01'],
            'branches' => ['1.x' => true, '2.x' => false],
        ],
        'omnitrade/stripe' => ['tags' => ['v1.4.0' => '2026-09-21'], 'branches' => ['1.x' => true]],
        'omnitrade/paypal' => ['tags' => [], 'branches' => ['main' => true]],
        'glitchr/omnibus' => ['tags' => [], 'branches' => ['2.x' => true, '1.x' => false]],
        'glitchr/omnibase' => ['tags' => [], 'branches' => ['3.x' => true]],
        'omnibase/blog' => ['tags' => [], 'branches' => ['main' => true]],
    ];

    public function __construct(Git2Service $git)
    {
        parent::__construct($git);
    }

    public function of(Package $package, int $commits = 5): PackageVersions
    {
        $repository = self::REPOSITORIES[$package->getName()] ?? null;
        if (null === $repository) {
            return new PackageVersions(publishedVersion: $package->getPublishedVersion());
        }

        $tags = [];
        foreach ($repository['tags'] as $name => $date) {
            $version = (string) PackageVersions::normalize($name);
            $tags[] = ['name' => $name, 'version' => $version, 'sha' => sha1($name), 'date' => new \DateTimeImmutable($date), 'stable' => PackageVersions::isStable($version)];
        }
        usort($tags, static fn (array $a, array $b): int => version_compare($b['version'], $a['version']));

        $branches = [];
        foreach ($repository['branches'] as $name => $default) {
            $branches[] = [
                'name' => $name,
                'version' => PackageVersions::branchVersion($name),
                'sha' => sha1($package->getName().$name),
                'date' => new \DateTimeImmutable('2026-10-09'),
                'default' => $default,
                'commits' => array_map(static fn (int $i): array => [
                    'sha' => substr(sha1($name.$i), 0, 7),
                    'subject' => sprintf('Commit %d of %s', $i, $name),
                    'date' => new \DateTimeImmutable('2026-10-0'.(9 - $i)),
                ], range(0, min($commits, 3) - 1)),
            ];
        }

        return new PackageVersions($tags, $branches, true, $package->getPublishedVersion());
    }
}
