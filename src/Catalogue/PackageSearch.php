<?php

namespace App\Catalogue;

use App\Entity\Package;
use App\Repository\PackageRepository;

use function Symfony\Component\String\u;

/**
 * The search over every package (as conda-forge.org/packages): one box, a
 * family and a kind to narrow it. Every word typed has to be found in the
 * package's name, description, keywords or family; a word in its name
 * counts more than one in its description, its exact short name ("stripe")
 * most. Without words, the packages come in the order of the floors - the
 * core, the families, the bridges, the prepared structures, the providers,
 * the JavaScript libraries - then by name.
 *
 * The catalogue holds a couple of hundred rows: it is read whole and sorted
 * here, and the page's script (search_controller.js) narrows the same rows
 * the same way as one types, from the haystack this class writes in each.
 */
final class PackageSearch
{
    /** The order of the floors when nothing is typed (and between equal scores). */
    public const ORDER = [Tier::Foundation, Tier::Family, Tier::Bridge, Tier::Component, Tier::Provider, Tier::Library];

    public function __construct(private readonly PackageRepository $packages)
    {
    }

    /**
     * @return list<Package> every package, in the order of the floors
     */
    public function all(): array
    {
        $rank = array_flip(array_map(static fn (Tier $t): string => $t->value, self::ORDER));
        $all = $this->packages->findCatalogue();
        usort($all, static fn (Package $a, Package $b): int => [$rank[$a->getTier()->value], $a->getName()] <=> [$rank[$b->getTier()->value], $b->getName()]);

        return $all;
    }

    /**
     * @return list<Package> the packages found, best first
     */
    public function search(string $q = '', ?string $family = null, ?string $kind = null): array
    {
        $tier = null !== $kind ? Tier::fromKind($kind) : null;
        $words = self::words($q);

        $found = [];
        foreach ($this->all() as $order => $package) {
            if (null !== $tier && $package->getTier() !== $tier) {
                continue;
            }
            if (null !== $family && '' !== $family && $package->getFamily() !== $family) {
                continue;
            }
            $score = self::score($package, $words);
            if (null !== $score) {
                $found[] = [$score, $order, $package];
            }
        }
        usort($found, static fn (array $a, array $b): int => [$b[0], $a[1]] <=> [$a[0], $b[1]]);

        return array_map(static fn (array $hit): Package => $hit[2], $found);
    }

    /**
     * How well a package answers the words; null when one of them is not found.
     *
     * @param list<string> $words
     */
    public static function score(Package $package, array $words): ?int
    {
        $name = self::fold($package->getName());
        $short = self::fold($package->getShortName());
        $haystack = self::haystack($package);
        $score = 0;
        foreach ($words as $word) {
            if (!str_contains($haystack, $word)) {
                return null;
            }
            $score += match (true) {
                $short === $word || $name === $word => 100,
                str_contains($name, $word) => 10,
                default => 1,
            };
        }

        return $score;
    }

    /** What a word is looked for in: the name, the family, the description, the keywords - lower case, without accents. */
    public static function haystack(Package $package): string
    {
        return self::fold(implode(' ', [$package->getName(), $package->getFamily() ?? '', $package->getDescription() ?? '', implode(' ', $package->getKeywords())]));
    }

    /** @return list<string> */
    public static function words(string $q): array
    {
        return array_values(array_filter(preg_split('/\s+/', self::fold($q)) ?: [], static fn (string $w): bool => '' !== $w));
    }

    public static function fold(string $text): string
    {
        return u($text)->ascii()->lower()->toString();
    }
}
