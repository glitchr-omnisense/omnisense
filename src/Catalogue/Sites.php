<?php

namespace App\Catalogue;

/**
 * The sites built on the ecosystem, for the Demos page and for "used by":
 * the folder each lives in (what the catalogue's `usedBy` holds), its name,
 * the port it answers on in development (plan 0 §2), and what kind of site
 * it is:
 *
 *   client   the site of a real person, business or studio
 *   product  a platform of the house, one instance for everyone
 *   demo     a DEMONSTRATION: the person or the firm it shows is fictional,
 *            and the page says so
 *
 * One sentence each is a text of the site (`sites.<slug>.text`). Taratata
 * is a private demonstration and is not listed.
 */
final class Sites
{
    public const CLIENT = 'client';
    public const PRODUCT = 'product';
    public const DEMO = 'demo';

    /** slug => [name, https port, kind] */
    private const ALL = [
        'glitchr' => ['Glitch Art', 8453, self::CLIENT],
        'chapaland-v2' => ['Chapaland', 443, self::PRODUCT],
        'latoucheoriginale' => ['La Touche Originale', 8444, self::CLIENT],
        'nakaya' => ['Nakaya Gourmet', 8443, self::CLIENT],
        'nakaya-traiteur' => ['Nakaya Traiteur', 8553, self::CLIENT],
        'meyers' => ['Meyers & Associée', 8463, self::CLIENT],
        'apfelschule' => ['Apfelschule', 8473, self::CLIENT],
        'anaelletourret' => ['Anaëlle Tourret', 8483, self::CLIENT],
        'brieucvourch' => ['Brieuc Vourch', 8493, self::CLIENT],
        'nakatani' => ['Keitaro Nakatani', 8503, self::CLIENT],
        'monica' => ['Monica Neagoy', 8513, self::CLIENT],
        'wineseller' => ['Amaury Kilicaslan', 8523, self::CLIENT],
        'panoboutique' => ['PANO Strasbourg', 8643, self::CLIENT],
        'wedding' => ['Convive', 8533, self::PRODUCT],
        'youtube' => ['Lucarne', 8613, self::PRODUCT],
        'maisondesante' => ['Maison de santé du Ried', 8543, self::DEMO],
        'notaire' => ['Étude Aldebert & Fontvielle', 8563, self::DEMO],
        'avocat' => ['Cabinet Maréval', 8573, self::DEMO],
        'juriste' => ['Côme Aubertel', 8583, self::DEMO],
    ];

    /** @return array<string, array{slug: string, name: string, url: string, kind: string, fictional: bool}> */
    public static function all(): array
    {
        $sites = [];
        foreach (self::ALL as $slug => [$name, $port, $kind]) {
            $sites[$slug] = [
                'slug' => $slug,
                'name' => $name,
                'url' => 'https://localhost'.(443 === $port ? '' : ':'.$port),
                'kind' => $kind,
                'fictional' => self::DEMO === $kind,
            ];
        }

        return $sites;
    }

    /** @return array{slug: string, name: string, url: string, kind: string, fictional: bool}|null */
    public static function get(string $slug): ?array
    {
        return self::all()[$slug] ?? null;
    }

    public static function name(string $slug): string
    {
        return self::ALL[$slug][0] ?? $slug;
    }
}
