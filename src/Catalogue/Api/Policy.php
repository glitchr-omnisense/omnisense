<?php

namespace App\Catalogue\Api;

use Omnisense\Contracts\Policy\UsagePolicy;

/**
 * A provider's usage policy as a card and the policies page show it, in
 * one shape whoever wrote it:
 *
 *   - declared: the provider package's own policy.json (its maintainer, or
 *     the company behind the API), read at its published head through
 *     omnisense/contracts' export (App\Catalogue\Api\DeclaredPolicies) -
 *     the data a site using the package is held to;
 *   - typed: what config/apis.yaml says of a provider wanted or reserved
 *     that has no package yet, read on the provider's own pages
 *     (fromTyped()), until its package declares its own.
 *
 * Unknown stays unknown: a permission is allowed, contract, forbidden or
 * unknown - the schema's - and a figure nobody read is null, never a yes.
 */
final readonly class Policy
{
    /** What it takes to call the provider: no key, a key, a user's OAuth consent, a contract. */
    public const ACCESS = ['none', 'key', 'oauth', 'contract'];

    /** omnisense/contracts' Permission, from the most open to the strictest. */
    public const PERMISSIONS = ['allowed', 'contract', 'forbidden', 'unknown'];

    /** Whether the provider asks to be credited. */
    public const ATTRIBUTION = ['required', 'per-item', 'not-required', 'unknown'];

    /** The typed file's words, read as the schema's. */
    private const TYPED_COMMERCIAL = ['yes' => 'allowed', 'no' => 'forbidden', 'conditional' => 'contract', 'unclear' => 'unknown'];
    private const TYPED_ATTRIBUTION = ['required' => 'required', 'per-item' => 'per-item', 'encouraged' => 'not-required', 'none' => 'not-required', 'unclear' => 'unknown'];

    /**
     * @param list<array{max: int, window: string, every: int, scope: string, per: ?string, operations: list<string>, unit: string, note: ?string}>|null $limits null: nobody knows them; []: the terms set none; per: the kind of resource a "resource" limit counts each one of apart
     * @param array<string, string>                                                                                                           $note   locale => the one thing to know (a typed policy)
     * @param list<string>                                                                                                                    $plans  the plans a policy declares, its limits those of $plan (the default)
     * @param array<string, list<array<string, mixed>>|null>                                                                                  $planLimits each plan's limits, by name
     * @param ?int                                                                                                                            $retention  how long the data may be kept at all, in seconds (rights.retention)
     */
    public function __construct(
        public bool $declared,
        public string $access,
        public ?array $limits,
        public string $commercial,
        public string $caching,
        public ?int $cacheTtl,
        public string $storage,
        public string $redistribution,
        public ?bool $openSource,
        public string $attribution,
        public ?string $credit,
        public ?string $creditUrl,
        public ?string $licence,
        public bool $userAgent,
        public string $declaredBy,
        public ?string $verifiedAt,
        public ?string $terms,
        public ?string $policyPage,
        public ?string $notes = null,
        public array $note = [],
        public ?string $ref = null,
        public ?string $file = null,
        public ?string $plan = null,
        public array $plans = [],
        public array $planLimits = [],
        public ?int $retention = null,
        public ?string $retentionCovers = null,
    ) {
    }

    /**
     * A policy its package declares. The schema says whether a key is needed, not how it is obtained: $access
     * (config/apis.yaml's `access:`) says "oauth" or "contract" where the site knows it.
     */
    public static function fromDeclared(UsagePolicy $policy, ?string $access = null, ?string $ref = null, ?string $file = null): self
    {
        // A policy with plans: the limits shown are its default plan's, the others named.
        $plans = null !== $policy->plans ? $policy->plans->names() : [];
        $plan = null !== $policy->plans ? $policy->plans->default : null;
        $data = (null !== $policy->plans ? $policy->forPlan(null) : $policy)->toArray();
        $rights = $data['rights'];
        $limitsOf = static fn (?array $limits): ?array => null === $limits ? null : array_map(static fn (array $l): array => [
            'max' => $l['max'], 'window' => $l['window'], 'every' => $l['every'], 'scope' => $l['scope'],
            // A limit counted per resource ("scope": "resource", omnisense/contracts 1.0.0-rc.1) names the kind it counts apart.
            'per' => $l['per'] ?? null, 'operations' => $l['operations'], 'unit' => $l['unit'], 'note' => $l['note'],
        ], $limits);
        $planLimits = [];
        foreach ($plans as $name) {
            $planLimits[$name] = $limitsOf($policy->forPlan($name)->toArray()['rateLimits']);
        }
        $attribution = $rights['attribution'];
        if (null !== $access && !\in_array($access, self::ACCESS, true)) {
            throw new \InvalidArgumentException(\sprintf('A provider\'s "access" is one of %s, "%s" given.', implode(', ', self::ACCESS), $access));
        }

        return new self(
            declared: true,
            access: $access ?? ($data['requirements']['key'] ? 'key' : 'none'),
            limits: $limitsOf($data['rateLimits']),
            commercial: $rights['commercial'],
            caching: $rights['caching']['permission'],
            cacheTtl: $rights['caching']['maxTtl'],
            storage: $rights['storage'],
            redistribution: $rights['redistribution'],
            openSource: $rights['openSourceRequired'],
            attribution: $attribution['required'] ? 'required' : 'not-required',
            credit: $attribution['text'],
            creditUrl: $attribution['url'],
            licence: $rights['licence'],
            userAgent: $data['requirements']['userAgent']['required'],
            declaredBy: $data['provenance']['declaredBy'],
            verifiedAt: $data['provenance']['verifiedAt'],
            terms: $data['provenance']['terms'],
            policyPage: $data['provenance']['policy'],
            notes: $data['provenance']['notes'],
            ref: $ref,
            file: $file,
            plan: $plan,
            plans: $plans,
            planLimits: $planLimits,
            retention: $rights['retention']['maxAge'] ?? null,
            retentionCovers: $rights['retention']['covers'] ?? null,
        );
    }

    /**
     * What config/apis.yaml says of a provider with no package yet: auth, commercial, attribution, credit, note,
     * terms, read. Its limits, caching, storage and redistribution were not typed: unknown.
     *
     * @param array<string, mixed> $data
     */
    public static function fromTyped(array $data): self
    {
        $pick = static function (string $key, array $allowed) use ($data): string {
            $value = (string) ($data[$key] ?? 'unclear');
            if (!\in_array($value, $allowed, true)) {
                throw new \InvalidArgumentException(\sprintf('A policy\'s "%s" is one of %s, "%s" given.', $key, implode(', ', $allowed), $value));
            }

            return $value;
        };
        if ($unknown = array_diff(array_keys($data), ['auth', 'commercial', 'attribution', 'credit', 'note', 'terms', 'read'])) {
            throw new \InvalidArgumentException(\sprintf('A typed policy has keys it does not know: %s.', implode(', ', $unknown)));
        }

        return new self(
            declared: false,
            access: $pick('auth', self::ACCESS),
            limits: null,
            commercial: self::TYPED_COMMERCIAL[$pick('commercial', array_keys(self::TYPED_COMMERCIAL))],
            caching: 'unknown',
            cacheTtl: null,
            storage: 'unknown',
            redistribution: 'unknown',
            openSource: null,
            attribution: self::TYPED_ATTRIBUTION[$pick('attribution', array_keys(self::TYPED_ATTRIBUTION))],
            credit: isset($data['credit']) ? (string) $data['credit'] : null,
            creditUrl: null,
            licence: null,
            userAgent: false,
            declaredBy: 'site',
            verifiedAt: isset($data['read']) ? (string) $data['read'] : null,
            terms: isset($data['terms']) ? (string) $data['terms'] : null,
            policyPage: null,
            note: array_map('strval', (array) ($data['note'] ?? [])),
        );
    }

    /** The one thing to know in a language: a typed note (a declaration's notes are its maintainer's, in English). */
    public function note(string $locale): ?string
    {
        return $this->note[$locale] ?? $this->note['en'] ?? null;
    }

    /** The page to read first: the terms, else the usage policy's page. */
    public function getSource(): ?string
    {
        return $this->terms ?? $this->policyPage;
    }

    /** A permission as the facts show it: yes, partial, no, unknown. */
    public static function fact(string $permission): string
    {
        return ['allowed' => 'yes', 'contract' => 'partial', 'forbidden' => 'no'][$permission] ?? 'unknown';
    }
}
