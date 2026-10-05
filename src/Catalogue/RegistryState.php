<?php

namespace App\Catalogue;

/**
 * What a registry (Packagist, npm) answered about a package: it is there
 * or it is not. Null from PackageRegistries::state() means the registry did
 * not answer - nothing is known, and what was known is kept.
 */
final class RegistryState
{
    public function __construct(
        public readonly bool $published,
        public readonly ?string $version = null,
        public readonly ?int $downloads = null,
    ) {
    }
}
