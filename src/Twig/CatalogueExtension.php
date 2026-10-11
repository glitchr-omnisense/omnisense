<?php

namespace App\Twig;

use App\Catalogue\Families;
use App\Entity\Package;
use App\Repository\PackageRepository;
use Base\Wikidoc\Manual\Manual;
use Base\Wikidoc\Manual\ManualRegistry;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

/**
 * What the templates ask of the catalogue: a package's manual, a manual's
 * package, a family's suffix, the manuals grouped as the catalogue.
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
            new TwigFunction('doc_path', $this->docPath(...)),
            new TwigFunction('manual_pages', $this->manualPages(...)),
            new TwigFunction('manual_groups', $this->groups(...)),
        ];
    }

    /**
     * The address, in the manuals, of a page of a manual at its default
     * version ("glitchr/omniscient/main/compatibilite"); null when the manual
     * is not there, so that a template can leave the link out.
     */
    public function docPath(string $manual, string $page = ''): ?string
    {
        $found = $this->manuals->get($manual);
        if (null === $found) {
            return null;
        }
        $version = $found->getDefaultVersion() ?? array_values($found->versions)[0] ?? null;

        return null === $version ? null : $found->urlPath($version->name, $page);
    }

    /**
     * The pages of a manual at its default version, in reading order, each
     * with its address: what the documentation's entry page lists for the
     * site's own pages (getting started, compatibility, contributing).
     *
     * @return list<array{title: string, path: string}>
     */
    public function manualPages(string $manual): array
    {
        $found = $this->manuals->get($manual);
        $version = $found?->getDefaultVersion() ?? ($found ? array_values($found->versions)[0] ?? null : null);
        if (null === $found || null === $version) {
            return [];
        }

        $pages = [];
        foreach ($this->manuals->pages($found, $version)->getSequence() as $page) {
            if (!$page->isSection()) {
                $pages[] = ['title' => $page->title, 'path' => $found->urlPath($version->name, $page->path)];
            }
        }

        return $pages;
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
            if ('glitchr/omniscient' === $key) {
                continue; // the site's own pages: the entry page shows them first, not among the packages
            }
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
