<?php

namespace App\Catalogue\Api;

use App\Catalogue\Catalogue;
use App\Catalogue\Package;
use App\Catalogue\Tier;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Yaml\Yaml;

/**
 * The families and the APIs of the ecosystem, as the cards of /apis, the
 * policies page and the wanted list of /contribute show them: config/apis.yaml
 * (statuses, capabilities, what is wanted), joined to the catalogue
 * (catalog/*.yaml: the released packages, their descriptions and
 * repositories) and to the usage policies the packages declare
 * (config/policies.json, DeclaredPolicies). A provider the catalogue lists
 * and the file does not is a card too. A released provider's policy is its
 * package's own, or "not declared yet": config/apis.yaml types a policy only
 * for a provider wanted or reserved, with no package yet - a `policy:` block
 * on a released one is an error. A family merged into another
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
        private readonly DeclaredPolicies $declared,
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

    /** @return array{families: int, providers: int, wanted: int, policies: int, declared: int, typed: int} */
    public function counts(): array
    {
        $providers = $this->providers();
        $declared = \count(array_filter($providers, static fn (Provider $p): bool => true === $p->policy?->declared));
        $typed = \count(array_filter($providers, static fn (Provider $p): bool => false === $p->policy?->declared));

        return [
            'families' => \count($this->families()),
            'providers' => \count($providers),
            'wanted' => \count($this->wanted()),
            'policies' => $declared + $typed,
            'declared' => $declared,
            'typed' => $typed,
        ];
    }

    /** A provider's policy: its package's declaration, or the one config/apis.yaml types for a provider with no package. */
    private function policyOf(string $package, ?Package $released, array $entry): ?Policy
    {
        if (null !== $declared = $this->declared->of($package)) {
            return Policy::fromDeclared($declared['policy'], isset($entry['access']) ? (string) $entry['access'] : null, $declared['ref'], $declared['file']);
        }
        if (!isset($entry['policy'])) {
            return null;
        }
        if (null !== $released) {
            throw new \InvalidArgumentException(\sprintf('%s: %s is released: its policy is its package\'s policy.json (app:policies:import), not a `policy:` block here.', $this->file, $package));
        }

        return Policy::fromTyped((array) $entry['policy']);
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
            // Each is a list of capabilities, or {capabilities, rename: {old short name: new}} when its packages are
            // republished under this family's vendor (omnireview/google -> omnipulse/google-reviews).
            $merged = [];
            $renames = [];
            foreach ((array) ($row['merged'] ?? []) as $from => $brought) {
                $brought = (array) $brought;
                $list = array_is_list($brought) ? $brought : ($brought['capabilities'] ?? []);
                $merged[(string) $from] = $this->capabilityList($list, $name.' (from '.$from.')');
                $renames[(string) $from] = array_is_list($brought) ? null : array_map('strval', (array) ($brought['rename'] ?? []));
            }

            $providers = [];
            // The released providers first, as the catalogue orders them; then those of the families merged into it.
            foreach ([$name => $released] + $merged as $source => $sourceCapabilities) {
                foreach ($this->catalogue->family($source)?->getMembers() ?? [] as $package) {
                    if (Tier::Provider !== $package->getTier()) {
                        continue;
                    }
                    $rename = $renames[$source] ?? null;
                    $short = null === $rename ? $package->getShortName() : ($rename[$package->getShortName()] ?? $package->getShortName());
                    $providers[$package->getName()] = new Provider(
                        package: $package->getName(),
                        family: $name,
                        name: $short,
                        status: Status::Available,
                        capabilities: $sourceCapabilities,
                        helpWanted: false,
                        policy: $this->policyOf($package->getName(), $package, []),
                        released: $package,
                        successor: null === $rename ? null : $name.'/'.$short,
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
                    policy: $this->policyOf($package, $released, (array) $entry),
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
