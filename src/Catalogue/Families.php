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
        'omnilab', 'omnilex', 'omnimeet', 'omnishield', 'omnisign', 'omnibill', 'omnireview', 'omniauth', 'omnidrive',
        'omnimap', 'omnisearch', 'omnishare', 'omnifund', 'omnishow', 'omniticket', 'omnireach', 'omnifilm', 'omnigame',
        'omnidns', 'omnibridge',
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
