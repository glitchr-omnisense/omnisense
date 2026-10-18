<?php

namespace App\Catalogue;

use App\Dto\InstallQuery;
use App\Entity\Package;
use App\Repository\PackageRepository;

/**
 * From the selector's rows to the packages they name: the core
 * (omniscient/omnibase, or glitchr/omnibase until it moves); the chosen
 * families' own packages and the providers chosen among theirs - checked
 * (`provider[]`, by whole name) or typed by short name ("stripe" in
 * omnitrade); a prepared structure; a bridge. Every name is the catalogue's
 * (app:packages:sync), never written here: omniscient/omnilab where the
 * family moved, glitchr/omnitrade where it has not yet.
 *
 * With nothing asked, the example: a family and one of its providers.
 */
final class InstallChoice
{
    /** The example shown before anything is chosen: the first of these found. */
    private const EXAMPLE = [['omnitrade', 'stripe'], ['omnibus', 'colissimo']];

    /** @var array<string, list<Package>>|null each family's providers, read once */
    private ?array $providers = null;

    public function __construct(private readonly PackageRepository $packages)
    {
    }

    public function resolve(InstallQuery $query, bool $asked): InstallSelection
    {
        $families = $this->families();
        $structures = array_map(static fn (Package $p): string => $p->getName(), $this->packages->findByTier(Tier::Component));
        $bridges = array_map(static fn (Package $p): string => $p->getName(), $this->packages->findByTier(Tier::Bridge));

        $need = \in_array($query->need, InstallQuery::NEEDS, true) ? $query->need : 'family';
        // In the page's order; the row not sent (an old link to another need), the first family.
        $askedFamilies = $query->families();
        $chosen = null === $askedFamilies
            ? array_filter([(string) array_key_first($families)])
            : array_values(array_filter(array_keys($families), static fn (string $f): bool => \in_array($f, $askedFamilies, true)));
        $picked = array_map('strval', $query->provider);
        $typed = trim($query->providers);
        $structure = \in_array($query->structure, $structures, true) ? $query->structure : (string) ($structures[0] ?? '');
        $bridge = \in_array($query->bridge, $bridges, true) ? $query->bridge : (string) ($bridges[0] ?? '');

        if (!$asked) {
            foreach (self::EXAMPLE as [$example, $provider]) {
                if (isset($families[$example])) {
                    [$chosen, $picked, $typed] = [[$example], [], $provider];
                    break;
                }
            }
        }

        // The providers chosen, among the chosen families' only: the chips checked, the words typed that name one.
        $offered = [];
        foreach ($chosen as $family) {
            foreach ($this->providersOf($family) as $provider) {
                $offered[$provider->getName()] = $provider;
            }
        }
        $picked = array_values(array_filter(array_unique($picked), static fn (string $name): bool => isset($offered[$name])));
        $unknown = [];
        foreach (preg_split('/[\s,;]+/', strtolower($typed)) ?: [] as $word) {
            if ('' === $word) {
                continue;
            }
            $short = str_contains($word, '/') ? substr($word, strpos($word, '/') + 1) : $word;
            $found = array_keys(array_filter($offered, static fn (Package $p): bool => $p->getName() === $word || $p->getShortName() === $short));
            if ($found) {
                $picked = array_values(array_unique([...$picked, ...$found]));
            } else {
                $unknown[] = $word;
            }
        }
        sort($picked);

        $names = match (true) {
            [] !== $query->p => array_filter(array_map('strval', $query->p)),
            'core' === $need => array_map(static fn (Package $p): string => $p->getName(), $this->packages->findByTier(Tier::Foundation)),
            'structure' === $need => array_filter([$structure]),
            'bridge' === $need => array_filter([$bridge]),
            default => [...array_map(static fn (string $f): string => $families[$f]->getName(), $chosen), ...$picked],
        };

        return new InstallSelection($need, $chosen, $picked, implode(' ', $unknown), $structure, $bridge, array_values(array_unique($names)), $unknown);
    }

    /**
     * A family's providers, by short name: the chips of the providers row.
     *
     * @return list<Package>
     */
    public function providersOf(string $family): array
    {
        if (null === $this->providers) {
            $this->providers = [];
            foreach (array_keys($this->families()) as $name) {
                $members = array_values(array_filter($this->packages->findFamily($name), static fn (Package $p): bool => Tier::Provider === $p->getTier()));
                usort($members, static fn (Package $a, Package $b): int => strcmp($a->getShortName(), $b->getShortName()));
                $this->providers[$name] = $members;
            }
        }

        return $this->providers[$family] ?? [];
    }

    /** @return array<string, Package> the families, omnibase apart (it is the core), in the home page's order */
    public function families(): array
    {
        $families = Families::ordered($this->packages->findFamilies());
        unset($families['omnibase']);

        return $families;
    }

    /**
     * The rows that choose one package: what a package page links to.
     *
     * @return array<string, string>
     */
    public static function paramsFor(Package $package): array
    {
        return match ($package->getTier()) {
            Tier::Foundation => ['need' => 'core'],
            Tier::Family => ['need' => 'family', 'family' => (string) $package->getFamily(), 'providers' => ''],
            Tier::Provider => ['need' => 'family', 'family' => (string) $package->getFamily(), 'providers' => $package->getShortName()],
            Tier::Component => ['framework' => Installer::SYMFONY, 'need' => 'structure', 'structure' => $package->getName()],
            Tier::Bridge => ['need' => 'bridge', 'bridge' => $package->getName()],
            Tier::Library => [],
        };
    }
}
