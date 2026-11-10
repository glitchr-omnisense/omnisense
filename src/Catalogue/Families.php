<?php

namespace App\Catalogue;

/**
 * What the site says of the families that the catalog files do not: the
 * order the home page turns the suffixes in. Which families EXIST is the
 * catalogue's (one catalog/<family>.yaml each); a family missing from ORDER
 * comes last, in alphabetical order.
 */
final class Families
{
    /** Omni + base, bus, trade...: the order the suffix turns in. */
    public const ORDER = [
        'omnibase', 'omnibus', 'omnitrade', 'omnistate', 'omnibank', 'omnisong', 'omnipost', 'omnifood', 'omnischolar',
        'omnilab', 'omnilex', 'omnimeet', 'omnishield', 'omnisign', 'omnibill', 'omniauth', 'omnidrive',
        'omnimap', 'omnisearch', 'omnishow', 'omniplay', 'omniticket', 'omnipulse', 'omnidns', 'omnibridge',
    ];

    /**
     * The families merged into another (2026-11-09): their packages are the successor's now, and their repositories
     * are archived. app:catalog:import lists none of them, whatever working copy is still on disk, and removes their
     * catalog file. omnifilm became omnishow's `work` contract, omnigame went into omniplay, omnishare became
     * omnipost's share links. On 2026-11-10 omnireach, omnireview and omnifund became omnipulse (audience,
     * reviews and funding: the pulse of a site or project), its providers published as omnipulse/<name>.
     */
    public const MERGED = [
        'omnifilm' => 'omnishow',
        'omnigame' => 'omniplay',
        'omnishare' => 'omnipost',
        'omnireach' => 'omnipulse',
        'omnireview' => 'omnipulse',
        'omnifund' => 'omnipulse',
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
}
