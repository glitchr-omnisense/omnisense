<?php

namespace App\Catalogue;

use App\Entity\Package;
use App\Repository\PackageRepository;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Brings the catalogue up to date with the repositories:
 *
 *  1. every package found by RepositoryScanner is created or rewritten -
 *     name, floor, family, description, licence, keywords, repository,
 *     branch, what it requires among the catalogue's packages, whether its
 *     README says it was tried against the real service, how many pages its
 *     docs/ holds;
 *  2. "used by": the sites whose composer.json or package.json requires it;
 *  3. its registry is asked whether it is published - unless `$registries`
 *     is false; when the registry does not answer, what was known is kept;
 *  4. a package whose repository is no longer there is marked missing.
 */
class Synchronizer
{
    public function __construct(
        private readonly RepositoryScanner $scanner,
        private readonly PackageRegistries $registries,
        private readonly PackageRepository $packages,
        private readonly EntityManagerInterface $em,
    ) {
    }

    /**
     * @param callable(string $name, string $what): void|null $progress
     */
    public function sync(bool $registries = true, bool $fresh = false, ?callable $progress = null): SyncReport
    {
        $report = new SyncReport();
        $now = new \DateTimeImmutable();

        $scanned = $this->scanner->scan();
        $sites = $this->scanner->sites();
        $report->sites = \count($sites);

        $known = [];
        foreach ($this->packages->findAll() as $package) {
            $known[$package->getName()] = $package;
        }

        foreach ($scanned as $name => $found) {
            $package = $known[$name] ?? null;
            $before = null !== $package ? self::fingerprint($package) : null;
            if (null === $package) {
                $package = new Package($name, $found->tier, $found->family);
                $this->em->persist($package);
            }

            [$verification, $note] = null !== $found->readme ? Verification::read($found->readme) : [Verification::Unstated, null];
            $usedBy = array_keys(array_filter($sites, static fn (array $requires): bool => \in_array($name, $requires, true)));

            $package
                ->setTier($found->tier)
                ->setFamily($found->family)
                ->setDescription($found->description)
                ->setLicense($found->license)
                ->setKeywords($found->keywords)
                ->setRepositoryUrl($found->repositoryUrl)
                ->setPath($found->path)
                ->setBranch($found->branch)
                ->setRequires(array_values(array_filter($found->requires, static fn (string $required): bool => isset($scanned[$required]))))
                ->setUsedBy(array_map('strval', $usedBy))
                ->setVerification($verification, $note)
                ->setReadme(null !== $found->readme)
                ->setDocPages($found->docPages)
                ->setMissing(false)
                ->setSyncedAt($now);

            if ($registries) {
                $state = $this->registries->state($name, $fresh);
                if (null !== $state) {
                    $package->setRegistryState($state->published, $state->version, $state->downloads, $now);
                    ++$report->registryAnswered;
                } else {
                    ++$report->registryUnreachable;
                }
            }

            if (null === $before) {
                $report->created[] = $name;
                $progress && $progress($name, 'created');
            } elseif ($before !== self::fingerprint($package)) {
                $report->updated[] = $name;
                $progress && $progress($name, 'updated');
            } else {
                ++$report->unchanged;
            }
        }

        foreach ($known as $name => $package) {
            if (!isset($scanned[$name]) && !$package->isMissing()) {
                $package->setMissing(true);
                $report->missing[] = $name;
                $progress && $progress($name, 'missing');
            }
        }

        $this->em->flush();

        return $report;
    }

    /** What a run may change, as one string: the same before and after means "unchanged". */
    private static function fingerprint(Package $package): string
    {
        return md5(serialize([
            $package->getTier()->value, $package->getFamily(), $package->getDescription(), $package->getLicense(), $package->getKeywords(),
            $package->getRepositoryUrl(), $package->getPath(), $package->getBranch(), $package->getRequires(), $package->getUsedBy(),
            $package->getVerification()->value, $package->getVerificationNote(), $package->hasReadme(), $package->getDocPages(), $package->isMissing(),
            $package->isPublished(), $package->getPublishedVersion(), $package->getDownloads(),
        ]));
    }
}
