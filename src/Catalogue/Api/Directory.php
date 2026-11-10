<?php

namespace App\Catalogue\Api;

use App\Catalogue\Catalogue;
use App\Catalogue\Tier;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Yaml\Yaml;

/**
 * The families and the APIs of the ecosystem, as the cards of /apis and the
 * wanted list of /contribute show them: config/apis.yaml (statuses,
 * capabilities, usage policies, what is wanted), joined to the catalogue
 * (catalog/*.yaml: the released packages, their descriptions and
 * repositories). A provider the catalogue lists and the file does not is a
 * card too, its policy not declared yet. A family merged into another
 * (`merged:`) has no card of its own: its released providers are listed under
 * the family it went into, with the capabilities it brought.
 *
 * The file is checked as it is read: a capability or a status it does not
 * declare is an error, so that a typo breaks the build, not a filter.
 */
class Directory
{
    /** @var array<string, Family>|null */
    private ?array $families = null;

    /** @var array<string, array<string, string>> */
    private array $labels = [];

    public function __construct(
        private readonly Catalogue $catalogue,
        #[Autowire('%app.apis_file%')] private readonly string $file,
    ) {
    }

    /** @return array<string, Family> by name, in the file's order */
    public function families(): array
    {
        if (null === $this->families) {
            $this->load();
        }

        return $this->families;
    }

    public function family(string $name): ?Family
    {
        return $this->families()[$name] ?? null;
    }

    /** @return list<Provider> every provider, family by family */
    public function providers(): array
    {
        return array_merge(...array_values(array_map(static fn (Family $f): array => $f->providers, $this->families())) ?: [[]]);
    }

    /** @return list<Family> the families of a status */
    public function byStatus(Status $status): array
    {
        return array_values(array_filter($this->families(), static fn (Family $f): bool => $f->status === $status));
    }

    /** @return list<Provider> the providers looking for a maintainer, in released families and new ones */
    public function wanted(): array
    {
        return array_values(array_filter($this->providers(), static fn (Provider $p): bool => $p->helpWanted));
    }

    /** A capability's label in a language. */
    public function label(string $capability, string $locale): string
    {
        $this->families();

        return $this->labels[$capability][$locale] ?? $this->labels[$capability]['en'] ?? $capability;
    }

    /** @return array<string, string> every capability used by a card, slug => label, sorted by label */
    public function capabilities(string $locale): array
    {
        $used = [];
        foreach ($this->families() as $family) {
            foreach ($family->capabilities as $capability) {
                $used[$capability] = $this->label($capability, $locale);
            }
        }
        asort($used, \SORT_FLAG_CASE | \SORT_STRING);

        return $used;
    }

    /** @return array{families: int, providers: int, wanted: int, policies: int} */
    public function counts(): array
    {
        $providers = $this->providers();

        return [
            'families' => \count($this->families()),
            'providers' => \count($providers),
            'wanted' => \count($this->wanted()),
            'policies' => \count(array_filter($providers, static fn (Provider $p): bool => null !== $p->policy)),
        ];
    }

    private function load(): void
    {
        $data = Yaml::parseFile($this->file);
        $this->labels = (array) ($data['capabilities'] ?? []);
        $families = [];

        foreach ((array) ($data['families'] ?? []) as $name => $row) {
            $name = (string) $name;
            $status = Status::tryFrom((string) ($row['status'] ?? '')) ?? throw new \InvalidArgumentException(\sprintf('%s: family "%s" has no valid status.', $this->file, $name));
            $capabilities = $this->capabilityList($row['capabilities'] ?? [], $name);
            $catalogued = $this->catalogue->family($name);
            // What the family's released providers answer, when the family grew capabilities they do not have.
            $released = $this->capabilityList($row['released_capabilities'] ?? $capabilities, $name);
            // The families merged into this one: their released providers are its own, with the capabilities they bring.
            $merged = [];
            foreach ((array) ($row['merged'] ?? []) as $from => $brought) {
                $merged[(string) $from] = $this->capabilityList($brought, $name.' (from '.$from.')');
            }

            $providers = [];
            // The released providers first, as the catalogue orders them; then those of the families merged into it.
            foreach ([$name => $released] + $merged as $source => $sourceCapabilities) {
                foreach ($this->catalogue->family($source)?->getMembers() ?? [] as $package) {
                    if (Tier::Provider !== $package->getTier()) {
                        continue;
                    }
                    $providers[$package->getName()] = new Provider(
                        package: $package->getName(),
                        family: $name,
                        name: $package->getShortName(),
                        status: Status::Available,
                        capabilities: $sourceCapabilities,
                        helpWanted: false,
                        policy: null,
                        released: $package,
                    );
                }
            }
            foreach ((array) ($row['providers'] ?? []) as $package => $entry) {
                $package = (string) $package;
                $released = $this->catalogue->find($package);
                $own = $this->capabilityList($entry['capabilities'] ?? $capabilities, $package);
                if ($stray = array_diff($own, $capabilities)) {
                    throw new \InvalidArgumentException(\sprintf('%s: %s has capabilities its family %s does not: %s.', $this->file, $package, $name, implode(', ', $stray)));
                }
                $providers[$package] = new Provider(
                    package: $package,
                    family: $name,
                    name: (string) ($entry['name'] ?? substr($package, (int) strpos($package, '/') + 1)),
                    status: null !== $released ? Status::Available : (Status::tryFrom((string) ($entry['status'] ?? '')) ?? throw new \InvalidArgumentException(\sprintf('%s: provider "%s" has no valid status.', $this->file, $package))),
                    capabilities: $own,
                    helpWanted: (bool) ($entry['help_wanted'] ?? false),
                    policy: isset($entry['policy']) ? Policy::fromArray((array) $entry['policy']) : null,
                    released: $released,
                );
            }

            $families[$name] = new Family(
                name: $name,
                status: $status,
                merged: array_keys($merged),
                note: array_map('strval', (array) ($row['note'] ?? [])),
                phrase: array_map('strval', (array) ($row['phrase'] ?? [])) ?: ($catalogued?->phrase ?? []),
                capabilities: $capabilities,
                helpWanted: (bool) ($row['help_wanted'] ?? false),
                providers: array_values($providers),
            );
        }

        $this->families = $families;
    }

    /** @return list<string> */
    private function capabilityList(mixed $list, string $who): array
    {
        $list = array_values(array_map('strval', (array) $list));
        if ($unknown = array_diff($list, array_keys($this->labels))) {
            throw new \InvalidArgumentException(\sprintf('%s: %s names capabilities the file does not declare: %s.', $this->file, $who, implode(', ', $unknown)));
        }

        return $list;
    }
}
