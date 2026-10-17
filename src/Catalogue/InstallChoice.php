<?php

namespace App\Catalogue;

use App\Dto\InstallQuery;
use App\Entity\Package;
use App\Repository\PackageRepository;

/**
 * From the selector's rows to the packages they name: the core
 * (omniscient/omnibase, or glitchr/omnibase until it moves); a family's own
 * package and the providers typed by their short name ("stripe" in
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

    public function __construct(private readonly PackageRepository $packages)
    {
    }

    public function resolve(InstallQuery $query, bool $asked): InstallSelection
    {
        $families = $this->families();
        $structures = array_map(static fn (Package $p): string => $p->getName(), $this->packages->findByTier(Tier::Component));
        $bridges = array_map(static fn (Package $p): string => $p->getName(), $this->packages->findByTier(Tier::Bridge));

        $need = \in_array($query->need, InstallQuery::NEEDS, true) ? $query->need : 'family';
        $family = isset($families[$query->family]) ? $query->family : (string) array_key_first($families);
        $providers = trim($query->providers);
        $structure = \in_array($query->structure, $structures, true) ? $query->structure : (string) ($structures[0] ?? '');
        $bridge = \in_array($query->bridge, $bridges, true) ? $query->bridge : (string) ($bridges[0] ?? '');

        if (!$asked) {
            foreach (self::EXAMPLE as [$example, $provider]) {
                if (isset($families[$example])) {
                    [$family, $providers] = [$example, $provider];
                    break;
                }
            }
        }

        $unknown = [];
        $names = match (true) {
            [] !== $query->p => array_filter(array_map('strval', $query->p)),
            'core' === $need => array_map(static fn (Package $p): string => $p->getName(), $this->packages->findByTier(Tier::Foundation)),
            'structure' === $need => array_filter([$structure]),
            'bridge' === $need => array_filter([$bridge]),
            default => $this->familyNames($families[$family] ?? null, $providers, $unknown),
        };

        return new InstallSelection($need, $family, $providers, $structure, $bridge, array_values($names), $unknown);
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

    /**
     * A family's own package, and the providers typed, matched on their short name (or whole name).
     *
     * @param list<string> $unknown filled with the words that name none of its providers
     *
     * @return list<string>
     */
    private function familyNames(?Package $own, string $typed, array &$unknown): array
    {
        if (null === $own) {
            return [];
        }
        $providers = [];
        foreach ($this->packages->findFamily((string) $own->getFamily()) as $member) {
            if (Tier::Provider === $member->getTier()) {
                $providers[$member->getShortName()] = $member->getName();
            }
        }

        $names = [$own->getName()];
        foreach (preg_split('/[\s,;]+/', strtolower($typed)) ?: [] as $word) {
            if ('' === $word) {
                continue;
            }
            $short = str_contains($word, '/') ? substr($word, strpos($word, '/') + 1) : $word;
            if (isset($providers[$short])) {
                $names[] = $providers[$short];
            } else {
                $unknown[] = $word;
            }
        }

        return array_values(array_unique($names));
    }
}
