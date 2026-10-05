<?php

namespace App\Catalogue;

/**
 * What the site says of the families that the repositories cannot: the
 * order the home page turns the suffixes in, and the names announced in the
 * registry of plan 0 (§3, §13) for which nothing is written yet.
 *
 * Which families EXIST is never read here: it is what the catalogue found
 * in a repository (PackageRepository::findFamilies()). A family listed in
 * ORDER and not found is simply not shown; one found and not listed comes
 * last. The sentence of each is a text of the site (`families.<name>.*` in
 * the translations).
 */
final class Families
{
    /** Omni + base, bus, trade...: the order the suffix turns in. */
    public const ORDER = ['omnibase', 'omnibus', 'omnitrade', 'omnistate', 'omnibank', 'omnisong', 'omnipost', 'omnifood', 'omnischolar', 'omnilex', 'omnimeet'];

    /**
     * Announced, not written: `planned` has a first site waiting for it,
     * `reserved` holds a name so that nobody writes the same thing under
     * another one. Shown as such, apart from the catalogue, and dropped
     * from this list by the page itself the day the family is found.
     */
    public const ANNOUNCED = [
        'omnishow' => 'planned',
        'omnisign' => 'planned',
        'omnibill' => 'planned',
        'omnireview' => 'planned',
        'omniticket' => 'reserved',
        'omnireach' => 'reserved',
        'omnifilm' => 'reserved',
        'omnigame' => 'reserved',
        'omnidns' => 'reserved',
    ];

    /** "omnitrade" → "trade": what turns after "Omni". */
    public static function suffix(string $family): string
    {
        return str_starts_with($family, 'omni') ? substr($family, 4) : $family;
    }

    /**
     * @template T
     *
     * @param array<string, T> $families keyed by family
     *
     * @return array<string, T> in the order of the home page
     */
    public static function ordered(array $families): array
    {
        $rank = array_flip(self::ORDER);
        uksort($families, static fn (string $a, string $b): int => [$rank[$a] ?? \PHP_INT_MAX, $a] <=> [$rank[$b] ?? \PHP_INT_MAX, $b]);

        return $families;
    }

    /**
     * The announced names that are still only names.
     *
     * @param list<string> $existing the families found in a repository
     *
     * @return array<string, string> name => planned|reserved
     */
    public static function announced(array $existing): array
    {
        return array_diff_key(self::ANNOUNCED, array_flip($existing));
    }
}
