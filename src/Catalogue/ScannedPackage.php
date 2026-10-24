<?php

namespace App\Catalogue;

/**
 * What a package's repository says of it, read by RepositoryScanner.
 */
final class ScannedPackage
{
    /**
     * @param list<string> $keywords
     * @param list<string> $requires every name its composer.json or package.json requires
     */
    public function __construct(
        public readonly string $name,
        public readonly Tier $tier,
        public readonly ?string $family,
        public readonly ?string $description,
        public readonly ?string $license,
        public readonly array $keywords,
        public readonly array $requires,
        public readonly string $path,
        public readonly ?string $branch,
        public readonly ?string $repository,
        public readonly ?string $readme,
        public readonly int $docPages,
        public readonly ?string $head = null,
    ) {
    }
}
