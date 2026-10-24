<?php

namespace App\Catalogue;

use Symfony\Component\DependencyInjection\Attribute\Autoconfigure;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Yaml\Yaml;

/**
 * The curated catalogue: every catalog/*.yaml file of the site, read once.
 * One file per family (catalog/omnilab.yaml: the family's own package and
 * its providers), catalog/omnibase.yaml for the core and the prepared
 * structures, catalog/omnibridge.yaml for the bridges.
 *
 * Public: static.php reads it from the container for the routes' parameters.
 */
#[Autoconfigure(public: true)]
class Catalogue
{
    /** @var array<string, Family>|null */
    private ?array $families = null;

    /** @var array<string, Package> */
    private array $packages = [];

    public function __construct(
        #[Autowire('%app.catalog_dir%')] private readonly string $dir,
    ) {
    }

    /** @return array<string, Family> by name, in the home page's order */
    public function families(): array
    {
        if (null === $this->families) {
            $this->load();
        }

        return $this->families;
    }

    public function family(string $name): ?Family
    {
        return $this->families()[$name] ?? null;
    }

    /** @return array<string, Package> every package by name, sorted by name */
    public function all(): array
    {
        $this->families();

        return $this->packages;
    }

    public function find(string $name): ?Package
    {
        return $this->all()[$name] ?? null;
    }

    public function findByKey(string $key): ?Package
    {
        foreach ($this->all() as $package) {
            if ($package->getKey() === $key) {
                return $package;
            }
        }

        return null;
    }

    /** @return list<Package> */
    public function findByTier(Tier $tier): array
    {
        return array_values(array_filter($this->all(), static fn (Package $p): bool => $p->getTier() === $tier));
    }

    /** @return list<Package> the packages that require this one */
    public function requiredBy(Package $package): array
    {
        return array_values(array_filter($this->all(), static fn (Package $other): bool => \in_array($package->getName(), $other->getRequires(), true)));
    }

    /**
     * The families that answer a question - their own package is a family
     * (not the core, not the bridges).
     *
     * @return array<string, Family>
     */
    public function contractFamilies(): array
    {
        return array_filter($this->families(), static fn (Family $f): bool => Tier::Family === $f->getOwn()?->getTier());
    }

    /** @return array{packages: int, families: int, providers: int, documented: int} */
    public function counts(): array
    {
        $all = $this->all();

        return [
            'packages' => \count($all),
            'families' => \count($this->contractFamilies()),
            'providers' => \count($this->findByTier(Tier::Provider)),
            'documented' => \count(array_filter($all, static fn (Package $p): bool => $p->isDocumented())),
        ];
    }

    private function load(): void
    {
        $families = [];
        $packages = [];
        $files = glob(rtrim($this->dir, '/').'/*.yaml') ?: [];
        sort($files);
        foreach ($files as $file) {
            $data = Yaml::parseFile($file);
            if (!\is_array($data)) {
                throw new \UnexpectedValueException(sprintf('%s is not a catalog file.', $file));
            }
            $name = (string) ($data['family'] ?? basename($file, '.yaml'));
            $members = [];
            foreach ((array) ($data['packages'] ?? []) as $packageName => $entry) {
                $package = Package::fromArray((string) $packageName, (array) $entry, $name);
                if (isset($packages[$package->getName()])) {
                    throw new \UnexpectedValueException(sprintf('Package "%s" is listed twice (%s).', $package->getName(), $file));
                }
                $packages[$package->getName()] = $members[] = $package;
            }
            $families[$name] = new Family($name, (array) ($data['phrase'] ?? []), (array) ($data['text'] ?? []), $members);
        }
        ksort($packages);

        $this->packages = $packages;
        $this->families = Families::ordered($families);
    }
}
