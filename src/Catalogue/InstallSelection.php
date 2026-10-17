<?php

namespace App\Catalogue;

/** What the selector's rows came to: each row's choice, the packages they name, the providers typed and not found. */
final readonly class InstallSelection
{
    /**
     * @param list<string> $names   the packages chosen, by name
     * @param list<string> $unknown the providers typed that the family does not have
     */
    public function __construct(
        public string $need,
        public string $family,
        public string $providers,
        public string $structure,
        public string $bridge,
        public array $names,
        public array $unknown = [],
    ) {
    }
}
