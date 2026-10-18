<?php

namespace App\Dto;

/**
 * The install selector's query string, row by row (as pytorch.org/get-started):
 * the framework, the channel, what is needed - the core, families (`family[]`,
 * or the old single `family=omnibus`) and some of their providers (`provider[]`
 * by whole name, the chips; or `providers` by short name, "stripe paypal",
 * typed or from an old link), a prepared structure, a bridge. `p[]` names
 * packages outright. `family` absent means "not said" (the first family);
 * present and empty, no family.
 */
final readonly class InstallQuery
{
    public const NEEDS = ['core', 'family', 'structure', 'bridge'];

    /**
     * @param list<string>|string|null $family
     * @param list<string>             $provider
     * @param list<string>             $p
     */
    public function __construct(
        public string $framework = 'bare',
        public string $channel = 'stable',
        public string $need = '',
        public array|string|null $family = null,
        public array $provider = [],
        public string $providers = '',
        public string $structure = '',
        public string $bridge = '',
        public array $p = [],
        public bool $fragment = false,
    ) {
    }

    /** @return list<string>|null the families asked, null when the row was not sent */
    public function families(): ?array
    {
        if (null === $this->family) {
            return null;
        }
        $asked = \is_array($this->family) ? $this->family : (preg_split('/[\s,]+/', $this->family) ?: []);

        return array_values(array_filter(array_map(static fn (mixed $f): string => \is_scalar($f) ? strtolower(trim((string) $f)) : '', $asked)));
    }
}
