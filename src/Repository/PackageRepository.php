<?php

namespace App\Repository;

use App\Catalogue\Tier;
use App\Entity\Package;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/** @extends ServiceEntityRepository<Package> */
class PackageRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Package::class);
    }

    public function findOneByHandle(string $handle): ?Package
    {
        return $this->findOneBy(['handle' => Package::handleOf($handle), 'missing' => false]);
    }

    /** @return list<Package> every package there is, by name */
    public function findCatalogue(): array
    {
        return $this->findBy(['missing' => false], ['handle' => 'ASC']);
    }

    /** @return list<Package> */
    public function findByTier(Tier $tier): array
    {
        return $this->findBy(['tier' => $tier, 'missing' => false], ['handle' => 'ASC']);
    }

    /** @return list<Package> a family's own package first, then its providers (or the foundation, then its components) */
    public function findFamily(string $family): array
    {
        $packages = $this->findBy(['family' => $family, 'missing' => false], ['handle' => 'ASC']);
        usort($packages, static fn (Package $a, Package $b): int => [$a->getTier()->order(), $a->getHandle()] <=> [$b->getTier()->order(), $b->getHandle()]);

        return $packages;
    }

    /**
     * The families that exist: those whose own package was found in a repository.
     *
     * @return array<string, Package> keyed by family ("omnitrade"), omnibase first, then by name
     */
    public function findFamilies(): array
    {
        $families = [];
        foreach ($this->findBy(['tier' => [Tier::Foundation, Tier::Family], 'missing' => false], ['handle' => 'ASC']) as $package) {
            $families[(string) $package->getFamily()] = $package;
        }
        uksort($families, static fn (string $a, string $b): int => ['omnibase' !== $a, $a] <=> ['omnibase' !== $b, $b]);

        return $families;
    }

    /** @return list<Package> the packages a site requires */
    public function findUsedBy(string $site): array
    {
        return array_values(array_filter($this->findCatalogue(), static fn (Package $p): bool => \in_array($site, $p->getUsedBy(), true)));
    }

    /** @return array{packages: int, manuals: int, published: int, unpublished: int, unknown: int, undocumented: int} */
    public function counts(): array
    {
        $counts = ['packages' => 0, 'manuals' => 0, 'published' => 0, 'unpublished' => 0, 'unknown' => 0, 'undocumented' => 0];
        foreach ($this->findCatalogue() as $package) {
            ++$counts['packages'];
            $counts['manuals'] += $package->hasManual() ? 1 : 0;
            $counts['undocumented'] += 0 === $package->getDocPages() ? 1 : 0;
            ++$counts[match ($package->isPublished()) {
                true => 'published',
                false => 'unpublished',
                null => 'unknown',
            }];
        }

        return $counts;
    }

    public function lastSync(): ?\DateTimeImmutable
    {
        $at = $this->createQueryBuilder('p')->select('MAX(p.syncedAt)')->getQuery()->getSingleScalarResult();

        return null !== $at ? new \DateTimeImmutable((string) $at) : null;
    }
}
