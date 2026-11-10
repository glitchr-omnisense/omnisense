<?php

namespace App\Catalogue\Api;

use Omnisense\Contracts\Policy\InvalidPolicyException;
use Omnisense\Contracts\Policy\UsagePolicy;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * The usage policies the providers' packages declare: config/policies.json,
 * omnisense/contracts' export (PolicyExport's shape: {schema, policies:
 * [{package, version, ref, file, policy}]}) that app:policies:import writes
 * from each package's policy.json at its published head.
 *
 * Each policy is read again by UsagePolicy::fromArray() as the file is
 * loaded: an entry the schema refuses - or one the export could not read,
 * listed with its error - is an exception, so that the build fails rather
 * than show a policy nobody can hold a site to.
 */
class DeclaredPolicies
{
    /** @var array<string, array{policy: UsagePolicy, ref: ?string, file: string}>|null */
    private ?array $policies = null;

    public function __construct(
        #[Autowire('%app.policies_file%')] private readonly string $file,
    ) {
    }

    /** @return array<string, array{policy: UsagePolicy, ref: ?string, file: string}> by package, its first policy file */
    public function all(): array
    {
        return $this->policies ??= $this->load();
    }

    /** @return array{policy: UsagePolicy, ref: ?string, file: string}|null */
    public function of(string $package): ?array
    {
        return $this->all()[$package] ?? null;
    }

    /** @return array<string, array{policy: UsagePolicy, ref: ?string, file: string}> */
    private function load(): array
    {
        if (!is_file($this->file)) {
            return [];
        }
        try {
            $data = json_decode((string) file_get_contents($this->file), true, 64, \JSON_THROW_ON_ERROR);
        } catch (\JsonException $e) {
            throw new InvalidPolicyException(\sprintf('%s is not JSON: %s.', $this->file, $e->getMessage()), 0, $e);
        }
        $entries = \is_array($data) ? ($data['policies'] ?? null) : null;
        if (!\is_array($entries) || !array_is_list($entries)) {
            throw new InvalidPolicyException(\sprintf('%s has no "policies" list.', $this->file));
        }

        $policies = [];
        foreach ($entries as $i => $entry) {
            $package = \is_array($entry) && \is_string($entry['package'] ?? null) ? $entry['package'] : throw new InvalidPolicyException(\sprintf('%s: policies[%d] names no package.', $this->file, $i));
            $file = \is_string($entry['file'] ?? null) ? $entry['file'] : 'policy.json';
            if (isset($entry['error'])) {
                throw new InvalidPolicyException(\sprintf('%s: %s\'s %s could not be read: %s', $this->file, $package, $file, (string) $entry['error']));
            }
            try {
                $policy = UsagePolicy::fromArray(\is_array($entry['policy'] ?? null) ? $entry['policy'] : throw new InvalidPolicyException('no policy.'));
            } catch (InvalidPolicyException $e) {
                throw new InvalidPolicyException(\sprintf('%s: %s\'s %s does not validate: %s', $this->file, $package, $file, $e->getMessage()), 0, $e);
            }
            $policies[$package] ??= ['policy' => $policy, 'ref' => \is_string($entry['ref'] ?? null) ? $entry['ref'] : null, 'file' => $file];
        }

        return $policies;
    }
}
