<?php

namespace App\Content;

/**
 * A page of a package's manual, or a section (a folder of pages): its
 * address inside the manual ("" for the home, "providers", "guides/install"),
 * its title, its file relative to the docs/ folder.
 */
final class DocPage
{
    /**
     * @param list<DocPage> $children
     */
    public function __construct(
        public readonly string $path,
        public readonly string $title,
        public readonly ?string $file,
        public readonly int $order = 100,
        public array $children = [],
    ) {
    }

    public function isSection(): bool
    {
        return null === $this->file;
    }

    /** Whether this page, or a page under it, is the one at $path. */
    public function contains(string $path): bool
    {
        if ($this->path === $path) {
            return true;
        }
        foreach ($this->children as $child) {
            if ($child->contains($path)) {
                return true;
            }
        }

        return false;
    }
}
