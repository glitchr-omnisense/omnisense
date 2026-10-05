<?php

namespace App\Catalogue;

/**
 * What a run of the catalogue's synchronisation did.
 */
final class SyncReport
{
    /** @var list<string> */
    public array $created = [];

    /** @var list<string> */
    public array $updated = [];

    /** @var list<string> packages whose repository is gone */
    public array $missing = [];

    public int $unchanged = 0;

    /** How many packages the registries answered about, and how many they did not. */
    public int $registryAnswered = 0;
    public int $registryUnreachable = 0;

    public int $sites = 0;

    public function total(): int
    {
        return \count($this->created) + \count($this->updated) + $this->unchanged;
    }
}
