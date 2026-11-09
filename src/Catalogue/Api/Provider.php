<?php

namespace App\Catalogue\Api;

use App\Catalogue\Package;

/**
 * One provider's card: config/apis.yaml's entry, the catalogue's package when
 * it is released, or both.
 */
final readonly class Provider
{
    /**
     * @param list<string> $capabilities
     */
    public function __construct(
        public string $package,
        public string $family,
        public string $name,
        public Status $status,
        public array $capabilities,
        public bool $helpWanted,
        public ?Policy $policy,
        public ?Package $released,
    ) {
    }

    public function getShortName(): string
    {
        return substr($this->package, (int) strpos($this->package, '/') + 1);
    }

    public function getDescription(): ?string
    {
        return $this->released?->getDescription();
    }

    /** Its repository: the catalogue's, or the one its reserved name points to. */
    public function getRepositoryUrl(): ?string
    {
        if (null !== $this->released) {
            return $this->released->getRepositoryUrl();
        }

        return Status::Reserved === $this->status ? 'https://github.com/glitchr-omnisense/'.str_replace('/', '-', $this->package) : null;
    }

    /** The auth a filter reads: the policy's, or "unknown" until it is declared. */
    public function getAuth(): string
    {
        return $this->policy?->auth ?? 'unknown';
    }

    /** The commercial use a filter reads: the policy's, or "unknown" until it is declared. */
    public function getCommercial(): string
    {
        return $this->policy?->commercial ?? 'unknown';
    }
}
