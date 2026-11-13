<?php

namespace App\Catalogue;

/**
 * A package as the catalogue describes it: one entry of a catalog/<family>.yaml
 * file. Nothing here is asked of a registry or a repository at request time:
 * the files are written by `app:catalog:import` from the repositories, then
 * curated by hand and reviewed in pull requests.
 */
final class Package implements \Stringable
{
    /**
     * @param list<string> $keywords
     * @param list<string> $requires the catalogue's packages it requires
     */
    public function __construct(
        private readonly string $name,
        private readonly Tier $tier,
        private readonly ?string $family = null,
        private readonly ?string $description = null,
        private readonly ?string $license = null,
        private readonly array $keywords = [],
        private readonly ?string $repository = null,
        private readonly ?string $branch = null,
        private readonly array $requires = [],
        private readonly Verification $verification = Verification::Unstated,
        private readonly ?string $verificationNote = null,
        private readonly bool $documented = false,
        private readonly ?string $version = null,
        private readonly bool|string $abandoned = false,
    ) {
    }

    /**
     * @param array<string, mixed> $data one entry of a catalog file
     */
    public static function fromArray(string $name, array $data, ?string $family): self
    {
        $floor = Tier::of($name);
        $tier = isset($data['kind']) ? (Tier::fromKind((string) $data['kind']) ?? throw new \InvalidArgumentException(sprintf('Package "%s": unknown kind "%s".', $name, $data['kind']))) : ($floor[0] ?? throw new \InvalidArgumentException(sprintf('Package "%s" is not a name of the ecosystem.', $name)));

        return new self(
            name: $name,
            tier: $tier,
            family: \array_key_exists('family', $data) ? $data['family'] : (null !== $floor ? $floor[1] : $family),
            description: $data['description'] ?? null,
            license: $data['license'] ?? null,
            keywords: array_values(array_map('strval', (array) ($data['keywords'] ?? []))),
            repository: $data['repository'] ?? null,
            branch: isset($data['branch']) ? (string) $data['branch'] : null,
            requires: array_values(array_map('strval', (array) ($data['requires'] ?? []))),
            verification: Verification::tryFrom((string) ($data['verification'] ?? '')) ?? Verification::Unstated,
            verificationNote: $data['verification_note'] ?? null,
            documented: (bool) ($data['docs'] ?? false),
            version: isset($data['version']) ? (string) $data['version'] : null,
            abandoned: \is_string($data['abandoned'] ?? null) ? $data['abandoned'] : (bool) ($data['abandoned'] ?? false),
        );
    }

    public function __toString(): string
    {
        return $this->name;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getVendor(): string
    {
        return explode('/', ltrim($this->name, '@'), 2)[0];
    }

    public function getShortName(): string
    {
        return explode('/', $this->name, 2)[1] ?? $this->name;
    }

    /**
     * The package's key in addresses: omnisense/omnilab is "omnilab" (the house
     * vendor dropped), omnibus/colissimo "omnibus-colissimo".
     */
    public function getKey(): string
    {
        return self::keyOf($this->name);
    }

    public static function keyOf(string $name): string
    {
        [$vendor, $short] = explode('/', ltrim($name, '@'), 2) + [1 => ''];

        return \in_array($vendor, Tier::HOUSE_VENDORS, true) ? $short : $vendor.'-'.$short;
    }

    public function isNpm(): bool
    {
        return str_starts_with($this->name, '@');
    }

    public function getTier(): Tier
    {
        return $this->tier;
    }

    public function getFamily(): ?string
    {
        return $this->family;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function getLicense(): ?string
    {
        return $this->license;
    }

    /** @return list<string> */
    public function getKeywords(): array
    {
        return $this->keywords;
    }

    /** "glitchr-omnisense/omnilab": where the package lives on GitHub. */
    public function getRepository(): ?string
    {
        return $this->repository;
    }

    public function getRepositoryUrl(): ?string
    {
        return null !== $this->repository ? 'https://github.com/'.$this->repository : null;
    }

    public function getBranch(): ?string
    {
        return $this->branch;
    }

    /** @return list<string> */
    public function getRequires(): array
    {
        return $this->requires;
    }

    public function getVerification(): Verification
    {
        return $this->verification;
    }

    public function getVerificationNote(): ?string
    {
        return $this->verificationNote;
    }

    /** Whether config/documentation.yaml lists its docs/ folder. */
    public function isDocumented(): bool
    {
        return $this->documented;
    }

    public function getRegistry(): string
    {
        return $this->isNpm() ? 'npm' : 'Packagist';
    }

    public function getRegistryUrl(): string
    {
        return $this->isNpm() ? 'https://www.npmjs.com/package/'.$this->name : 'https://packagist.org/packages/'.$this->name;
    }

    /** Its latest stable release on Packagist ("1.0.0"); null while nothing is tagged. */
    public function getVersion(): ?string
    {
        return $this->version;
    }

    public function isStable(): bool
    {
        return null !== $this->version;
    }

    public function isAbandoned(): bool
    {
        return false !== $this->abandoned;
    }

    /** The package to require instead, when Packagist names one. */
    public function getReplacement(): ?string
    {
        return \is_string($this->abandoned) ? $this->abandoned : null;
    }

    /**
     * The constraint a project writes: ^1.0 for a stable release 1.0.0 (what
     * `composer require` writes by itself); while nothing is tagged, its
     * development branch's: 1.x → 1.x-dev, 2.0 → 2.0.x-dev, main → dev-main.
     */
    public function getConstraint(): ?string
    {
        if (null !== $this->version && 1 === preg_match('/^(\d+)\.(\d+)/', $this->version, $m)) {
            return '^'.$m[1].'.'.$m[2];
        }

        return match (true) {
            null === $this->branch => null,
            1 === preg_match('/^\d+\.x$/', $this->branch) => $this->branch.'-dev',
            1 === preg_match('/^\d+\.\d+$/', $this->branch) => $this->branch.'.x-dev',
            default => 'dev-'.$this->branch,
        };
    }

    /**
     * A stable package installs with the default minimum-stability and no
     * constraint (Composer writes ^X.Y); an untagged one names its branch,
     * which a project accepts only with a development minimum-stability.
     */
    public function getInstallCommand(): string
    {
        if ($this->isNpm()) {
            return 'npm install '.$this->name;
        }
        $constraint = $this->isStable() ? null : $this->getConstraint();

        return 'composer require '.$this->name.(null !== $constraint ? ':'.$constraint : '');
    }
}
