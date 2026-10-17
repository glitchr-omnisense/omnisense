<?php

namespace App\Dto;

/**
 * The install selector's query string, row by row (as pytorch.org/get-started):
 * the framework, the channel, what is needed - the core, a family (and some
 * of its providers, by short name: "stripe paypal"), a prepared structure, a
 * bridge. `p[]` names packages outright: the address a package page links to.
 */
final readonly class InstallQuery
{
    public const NEEDS = ['core', 'family', 'structure', 'bridge'];

    /**
     * @param list<string> $p
     */
    public function __construct(
        public string $framework = 'bare',
        public string $channel = 'stable',
        public string $need = '',
        public string $family = '',
        public string $providers = '',
        public string $structure = '',
        public string $bridge = '',
        public array $p = [],
        public bool $fragment = false,
    ) {
    }
}
