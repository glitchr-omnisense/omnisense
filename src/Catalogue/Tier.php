<?php

namespace App\Catalogue;

/**
 * The floor a package stands on (plan 0, rules 1 to 3):
 *
 *   foundation  glitchr/omnibase: what every site starts from
 *   component   omnibase/<trade>: a site's component, one per trade
 *   family      glitchr/omniXXX: one question asked of several services
 *   provider    omniXXX/<provider>: one service answering it
 *   library     a JavaScript library of the house (@glitchr/...)
 */
enum Tier: string
{
    case Foundation = 'foundation';
    case Component = 'component';
    case Family = 'family';
    case Provider = 'provider';
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

            return 1 === preg_match('/^omni[a-z]+$/', $package) ? [self::Family, $package] : null;
        }
        if ('omnibase' === $vendor) {
            return [self::Component, 'omnibase'];
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
            self::Library => 4,
        };
    }
}
