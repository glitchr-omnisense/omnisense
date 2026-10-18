<?php

namespace App\Catalogue;

/** What the selector's rows came to: each row's choice, the packages they name, the providers typed and not found. */
final readonly class InstallSelection
{
    /**
     * @param list<string> $families the families chosen, in the page's order
     * @param list<string> $picked   the providers chosen, by whole name (the chips checked)
     * @param string       $providers the words typed that name no provider (left in the field)
     * @param list<string> $names    the packages chosen, by name
     * @param list<string> $unknown  the providers typed that none of the families has
     */
    public function __construct(
        public string $need,
        public array $families,
        public array $picked,
        public string $providers,
        public string $structure,
        public string $bridge,
        public array $names,
        public array $unknown = [],
    ) {
    }

    /** The families as the answer names them: "omnibus + omnilex". */
    public function familyList(): string
    {
        return implode(' + ', $this->families);
    }
}
