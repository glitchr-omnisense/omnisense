<?php

namespace App\Content;

/**
 * A package's documentation as config/documentation.yaml declares it: its
 * key in the addresses (/docs/<key>/<series>/...), its package, its GitHub
 * repository, its default series and every series with the commit it is
 * read at.
 */
final class Manual
{
    /**
     * @param array<string, array{branch: ?string, ref: string, pinned: ?string, path: string, eol: bool}> $versions series => source
     */
    public function __construct(
        public readonly string $key,
        public readonly string $package,
        public readonly string $repository,
        public readonly string $default,
        public readonly array $versions,
        /** The package's own website, when it has one (config/documentation.yaml's `website`). */
        public readonly ?string $website = null,
    ) {
    }

    /** @return list<string> */
    public function series(): array
    {
        return array_keys($this->versions);
    }

    public function has(string $series): bool
    {
        return isset($this->versions[$series]);
    }

    /** Whether a series is the current one (the default): the others are shown with a notice, out of the sitemap, not indexed. */
    public function isCurrent(string $series): bool
    {
        return $series === $this->default;
    }
}
