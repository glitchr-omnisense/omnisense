<?php

namespace App\Twig;

use App\Catalogue\Families;
use App\Catalogue\Sites;
use App\Entity\Package;
use App\Repository\PackageRepository;
use Base\Wikidoc\Manual\Manual;
use Base\Wikidoc\Manual\ManualRegistry;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

/**
 * What the templates ask of the catalogue: a package's manual, a manual's
 * package, a family's suffix, a site by its folder.
 */
class CatalogueExtension extends AbstractExtension
{
    public function __construct(
        private readonly ManualRegistry $manuals,
        private readonly PackageRepository $packages,
    ) {
    }

    public function getFunctions(): array
    {
        return [
            new TwigFunction('manual_of', fn (Package $package): ?Manual => $this->manuals->get($package->getHandle())),
            new TwigFunction('package_of', fn (Manual|string $manual): ?Package => $this->packages->findOneByHandle($manual instanceof Manual ? $manual->getKey() : $manual)),
            new TwigFunction('family_suffix', Families::suffix(...)),
            new TwigFunction('site_of', Sites::get(...)),
            new TwigFunction('manual_groups', $this->groups(...)),
        ];
    }

    /**
     * The manuals as the catalogue groups its packages: omnibase and its
     * components, then each family with its providers, then the libraries,
     * then what the catalogue does not know.
     *
     * @param array<string, Manual> $manuals
     *
     * @return list<array{family: ?string, kind: string, manuals: list<array{manual: Manual, package: ?Package}>}>
     */
    public function groups(array $manuals): array
    {
        $packages = [];
        foreach ($this->packages->findCatalogue() as $package) {
            $packages[$package->getHandle()] = $package;
        }

        $groups = [];
        foreach ($manuals as $key => $manual) {
            $package = $packages[$key] ?? null;
            $group = match (true) {
                null === $package => '~other',
                null === $package->getFamily() => '~library',
                default => $package->getFamily(),
            };
            $groups[$group][] = ['manual' => $manual, 'package' => $package];
        }

        $ordered = [];
        foreach (array_keys(Families::ordered($groups)) as $name) {
            $members = $groups[$name];
            usort($members, static fn (array $a, array $b): int => [$a['package']?->getTier()->order() ?? 9, $a['manual']->getKey()] <=> [$b['package']?->getTier()->order() ?? 9, $b['manual']->getKey()]);
            $ordered[] = [
                'family' => str_starts_with($name, '~') ? null : $name,
                'kind' => match ($name) {
                    '~library' => 'library',
                    '~other' => 'other',
                    default => 'family',
                },
                'manuals' => $members,
            ];
        }

        return $ordered;
    }
}
