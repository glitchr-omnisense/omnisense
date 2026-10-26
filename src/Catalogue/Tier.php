<?php

namespace App\Catalogue;

/**
 * The floor a package stands on (plan 0, rules 1 to 3):
 *
 *   foundation  omnisense/omnibase (glitchr/omnibase until it moves): the
 *               core every site starts from
 *   component   omnibase/<trade>: a prepared structure - ready-made for a
 *               trade (entities, screens, back office, translations,
 *               fixtures), assembled on the core; optional, nothing in the
 *               core or the families depends on one
 *   family      omnisense/omniXXX (glitchr/omniXXX until it moves): one
 *               contract for one domain
 *   provider    omniXXX/<service>: one service fulfilling it
 *   bridge      omnibridge/<target>: the glue between omnibase and a
 *               framework, a storage or a view layer (plan 0, rule 13)
 *   library     a JavaScript library of the house (@glitchr/...)
 */
enum Tier: string
{
    case Foundation = 'foundation';
    case Component = 'component';
    case Family = 'family';
    case Provider = 'provider';
    case Bridge = 'bridge';
    case Library = 'library';

    /**
     * The vendors of the core and of the families' own packages: omnisense/
     * on Packagist (omnisense/omnilab first, the others as they move), the
     * old glitchr/ name kept as a `replace` and read the same while both exist.
     */
    public const HOUSE_VENDORS = ['omnisense', 'glitchr'];

    /**
     * The floor a package's name puts it on, and the family it belongs to
     * ("omnitrade" for glitchr/omnitrade and omnitrade/stripe, "omnilab" for
     * omnisense/omnilab, "omnibase" for glitchr/omnibase and omnibase/blog);
     * null for a name that is none of the ecosystem's.
     *
     * @return array{0: self, 1: ?string}|null
     */
    public static function of(string $name): ?array
    {
        $name = strtolower($name);
        if (str_starts_with($name, '@')) {
            return str_starts_with($name, '@glitchr/') ? [self::Library, null] : null;
        }
        if (!str_contains($name, '/')) {
            return null;
        }
        [$vendor, $package] = explode('/', $name, 2);

        if (\in_array($vendor, self::HOUSE_VENDORS, true)) {
            if ('omnibase' === $package) {
                return [self::Foundation, 'omnibase'];
            }

            if ('omnibridge' === $package) {
                return [self::Bridge, null];
            }

            return 1 === preg_match('/^omni[a-z]+$/', $package) ? [self::Family, $package] : null;
        }
        if ('omnibase' === $vendor) {
            return [self::Component, 'omnibase'];
        }
        if ('omnibridge' === $vendor) {
            return [self::Bridge, null];
        }

        return 1 === preg_match('/^omni[a-z]+$/', $vendor) ? [self::Provider, $vendor] : null;
    }

    /**
     * What the search calls it (its `kind` filter): the core, a family, a
     * provider, a prepared structure, a bridge, a JavaScript library.
     */
    public function kind(): string
    {
        return match ($this) {
            self::Foundation => 'core',
            self::Component => 'structure',
            default => $this->value,
        };
    }

    public static function fromKind(string $kind): ?self
    {
        foreach (self::cases() as $tier) {
            if ($tier->kind() === $kind) {
                return $tier;
            }
        }

        return null;
    }

    public function order(): int
    {
        return match ($this) {
            self::Foundation => 0,
            self::Component => 1,
            self::Family => 2,
            self::Provider => 3,
            self::Bridge => 4,
            self::Library => 5,
        };
    }
}
