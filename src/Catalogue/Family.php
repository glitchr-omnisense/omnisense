<?php

namespace App\Catalogue;

/**
 * A catalog/<name>.yaml file: a family (or the bridges, or the core and its
 * prepared structures), its sentence in each language, and its packages.
 */
final class Family
{
    /**
     * @param array<string, string> $phrase   locale => one line
     * @param array<string, string> $text     locale => a paragraph
     * @param list<Package>         $packages
     */
    public function __construct(
        public readonly string $name,
        public readonly array $phrase,
        public readonly array $text,
        public readonly array $packages,
    ) {
    }

    /** "omnitrade" → "trade": what turns after "Omni". */
    public function getSuffix(): string
    {
        return Families::suffix($this->name);
    }

    public function phrase(string $locale): string
    {
        return $this->phrase[$locale] ?? $this->phrase['en'] ?? array_values($this->phrase)[0] ?? '';
    }

    public function text(string $locale): string
    {
        return $this->text[$locale] ?? $this->text['en'] ?? array_values($this->text)[0] ?? '';
    }

    /** Whether the family has a page: its own package is a family or the core (not the bridges, not a family only reserved). */
    public function hasPage(): bool
    {
        return null !== $this->getOwn();
    }

    /** The family's own package (omnisense/omnilab, omnisense/omnibase), if it has one. */
    public function getOwn(): ?Package
    {
        foreach ($this->packages as $package) {
            if (\in_array($package->getTier(), [Tier::Family, Tier::Foundation], true)) {
                return $package;
            }
        }

        return null;
    }

    /** @return list<Package> every package but its own */
    public function getMembers(): array
    {
        $own = $this->getOwn();

        return array_values(array_filter($this->packages, static fn (Package $p): bool => $p !== $own));
    }
}
