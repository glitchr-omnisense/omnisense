<?php

namespace App\Catalogue\Api;

/** One family's card: what it answers, its capabilities, its providers. */
final readonly class Family
{
    /**
     * @param array<string, string> $phrase       locale => one line
     * @param list<string>          $capabilities
     * @param list<Provider>        $providers
     * @param list<string>          $merged       the families merged into this one
     * @param array<string, string> $note         locale => one more thing to know
     */
    public function __construct(
        public string $name,
        public Status $status,
        public array $phrase,
        public array $capabilities,
        public bool $helpWanted,
        public array $providers,
        public array $merged = [],
        public array $note = [],
    ) {
    }

    public function note(string $locale): ?string
    {
        return $this->note[$locale] ?? $this->note['en'] ?? null;
    }

    public function phrase(string $locale): string
    {
        return $this->phrase[$locale] ?? $this->phrase['en'] ?? '';
    }

    /** @return list<Provider> the providers looking for a maintainer */
    public function getWanted(): array
    {
        return array_values(array_filter($this->providers, static fn (Provider $p): bool => $p->helpWanted));
    }
}
