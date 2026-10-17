<?php

namespace App\Dto;

/** The search's query string: /packages?q=stripe&family=omnitrade&kind=provider. */
final readonly class PackageQuery
{
    public function __construct(
        public string $q = '',
        public string $family = '',
        public string $kind = '',
    ) {
    }

    public function isEmpty(): bool
    {
        return '' === trim($this->q) && '' === $this->family && '' === $this->kind;
    }
}
