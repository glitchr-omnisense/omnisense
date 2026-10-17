<?php

namespace App\Catalogue;

/**
 * The floor a package stands on (plan 0, rules 1 to 3):
 *
 *   foundation  glitchr/omnibase: the core every site starts from
 *   component   omnibase/<extension>: an extension of the core (a trade, or
 *               a feature several sites share), written framework-agnostic
 *   family      glitchr/omniXXX: one contract for one domain
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
     * The floor a package's name puts it on, and the family it belongs to
     * ("omnitrade" for glitchr/omnitrade and omnitrade/stripe, "omnibase"
     * for glitchr/omnibase and omnibase/blog); null for a name that is none
     * of the ecosystem's.
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

        if ('glitchr' === $vendor) {
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
