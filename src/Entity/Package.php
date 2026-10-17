<?php

namespace App\Entity;

use App\Catalogue\Tier;
use App\Catalogue\Verification;
use App\Repository\PackageRepository;
use Doctrine\ORM\Mapping as ORM;

/**
 * A package of the ecosystem, as `app:packages:sync` read it: its
 * composer.json or package.json in its repository (name, description,
 * licence, what it requires), its README (was it tried against the real
 * service?), its repository (the branch checked out, where it is
 * published), the registry (is it on Packagist or npm?), and the sites
 * whose composer.json requires it.
 *
 * Nothing here is typed by hand: a run of the command writes every field,
 * and a package whose repository is gone is marked missing, not deleted -
 * its followers keep their line until it comes back or is removed.
 */
#[ORM\Entity(repositoryClass: PackageRepository::class)]
#[ORM\Table(name: 'catalogue_package')]
#[ORM\Index(name: 'catalogue_package_family', columns: ['family', 'tier'])]
class Package
{
    public const PACKAGIST = 'packagist';
    public const NPM = 'npm';

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    protected $id;

    /** The package's name as its registry spells it: "glitchr/omnitrade", "@glitchr/flowjs". */
    #[ORM\Column(length: 120, unique: true)]
    protected string $name;

    /**
     * Its name in an address: lower case, without the "@" of an npm scope -
     * "glitchr/flowjs". Not called a slug: it holds a slash, and the back
     * office would take a slug for the record's address.
     */
    #[ORM\Column(length: 120, unique: true)]
    protected string $handle;

    #[ORM\Column(length: 12)]
    protected string $registry = self::PACKAGIST;

    #[ORM\Column(length: 16, enumType: Tier::class)]
    protected Tier $tier;

    /** "omnitrade" for glitchr/omnitrade and its providers, "omnibase" for the foundation and its components; null for a library. */
    #[ORM\Column(length: 40, nullable: true)]
    protected ?string $family = null;

    #[ORM\Column(type: 'text', nullable: true)]
    protected ?string $description = null;

    #[ORM\Column(length: 80, nullable: true)]
    protected ?string $license = null;

    /** @var list<string> */
    #[ORM\Column(type: 'json')]
    protected array $keywords = [];

    /** The repository's web address ("https://github.com/glitchr-studio/omnitrade"), from its origin remote. */
    #[ORM\Column(length: 255, nullable: true)]
    protected ?string $repositoryUrl = null;

    /** Its folder under the repositories' mount: "omnitrade/core". */
    #[ORM\Column(length: 160)]
    protected string $path = '';

    /** The branch checked out. */
    #[ORM\Column(length: 60, nullable: true)]
    protected ?string $branch = null;

    /** @var list<string> the catalogue's packages this one requires */
    #[ORM\Column(type: 'json')]
    protected array $requires = [];

    /** @var list<string> the sites whose composer.json or package.json requires it (their folder's name) */
    #[ORM\Column(type: 'json')]
    protected array $usedBy = [];

    /** On its registry: true, false, or null while the registry has never answered. */
    #[ORM\Column(type: 'boolean', nullable: true)]
    protected ?bool $published = null;

    /** The newest version the registry serves ("1.x-dev", "1.4.0"). */
    #[ORM\Column(length: 60, nullable: true)]
    protected ?string $publishedVersion = null;

    #[ORM\Column(type: 'integer', nullable: true)]
    protected ?int $downloads = null;

    /** When the registry last answered about it. */
    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    protected ?\DateTimeImmutable $registryCheckedAt = null;

    #[ORM\Column(length: 12, enumType: Verification::class)]
    protected Verification $verification = Verification::Unstated;

    /** The README's own sentence the state was read in. */
    #[ORM\Column(type: 'text', nullable: true)]
    protected ?string $verificationNote = null;

    #[ORM\Column(type: 'boolean')]
    protected bool $readme = false;

    /** How many Markdown pages its docs/ folder holds. */
    #[ORM\Column(type: 'integer')]
    protected int $docPages = 0;

    /** Its repository was not found at the last run. */
    #[ORM\Column(type: 'boolean')]
    protected bool $missing = false;

    #[ORM\Column(type: 'datetime_immutable')]
    protected \DateTimeImmutable $syncedAt;

    public function __construct(string $name, Tier $tier, ?string $family = null)
    {
        $this->name = $name;
        $this->handle = self::handleOf($name);
        $this->registry = str_starts_with($name, '@') ? self::NPM : self::PACKAGIST;
        $this->tier = $tier;
        $this->family = $family;
        $this->syncedAt = new \DateTimeImmutable();
    }

    public static function handleOf(string $name): string
    {
        return strtolower(trim(ltrim(trim($name), '@'), '/'));
    }

    public function __toString(): string
    {
        return $this->name;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getHandle(): string
    {
        return $this->handle;
    }

    /** "glitchr" */
    public function getVendor(): string
    {
        return explode('/', $this->handle, 2)[0];
    }

    /** "omnitrade", "stripe": what follows the vendor. */
    public function getShortName(): string
    {
        return explode('/', $this->handle, 2)[1] ?? $this->handle;
    }

    public function getRegistry(): string
    {
        return $this->registry;
    }

    public function isNpm(): bool
    {
        return self::NPM === $this->registry;
    }

    public function getTier(): Tier
    {
        return $this->tier;
    }

    public function setTier(Tier $tier): static
    {
        $this->tier = $tier;

        return $this;
    }

    public function getFamily(): ?string
    {
        return $this->family;
    }

    public function setFamily(?string $family): static
    {
        $this->family = $family;

        return $this;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(?string $description): static
    {
        $this->description = null !== $description && '' !== trim($description) ? trim($description) : null;

        return $this;
    }

    public function getLicense(): ?string
    {
        return $this->license;
    }

    public function setLicense(?string $license): static
    {
        $this->license = $license;

        return $this;
    }

    /** @return list<string> */
    public function getKeywords(): array
    {
        return $this->keywords;
    }

    /** @param list<string> $keywords */
    public function setKeywords(array $keywords): static
    {
        $this->keywords = array_values(array_unique(array_map('strval', $keywords)));

        return $this;
    }

    public function getRepositoryUrl(): ?string
    {
        return $this->repositoryUrl;
    }

    public function setRepositoryUrl(?string $repositoryUrl): static
    {
        $this->repositoryUrl = $repositoryUrl;

        return $this;
    }

    public function getPath(): string
    {
        return $this->path;
    }

    public function setPath(string $path): static
    {
        $this->path = $path;

        return $this;
    }

    public function getBranch(): ?string
    {
        return $this->branch;
    }

    public function setBranch(?string $branch): static
    {
        $this->branch = $branch;

        return $this;
    }

    /** @return list<string> */
    public function getRequires(): array
    {
        return $this->requires;
    }

    /** @param list<string> $requires */
    public function setRequires(array $requires): static
    {
        sort($requires);
        $this->requires = array_values(array_unique($requires));

        return $this;
    }

    /** @return list<string> */
    public function getUsedBy(): array
    {
        return $this->usedBy;
    }

    /** @param list<string> $usedBy */
    public function setUsedBy(array $usedBy): static
    {
        sort($usedBy);
        $this->usedBy = array_values(array_unique($usedBy));

        return $this;
    }

    public function isPublished(): ?bool
    {
        return $this->published;
    }

    public function getPublishedVersion(): ?string
    {
        return $this->publishedVersion;
    }

    public function getDownloads(): ?int
    {
        return $this->downloads;
    }

    public function getRegistryCheckedAt(): ?\DateTimeImmutable
    {
        return $this->registryCheckedAt;
    }

    /** What the registry answered, on the day it did. */
    public function setRegistryState(bool $published, ?string $version, ?int $downloads, \DateTimeImmutable $at): static
    {
        $this->published = $published;
        $this->publishedVersion = $published ? $version : null;
        $this->downloads = $published ? $downloads : null;
        $this->registryCheckedAt = $at;

        return $this;
    }

    /** Its page on its registry - whether or not the package is there yet. */
    public function getRegistryUrl(): string
    {
        return $this->isNpm() ? 'https://www.npmjs.com/package/'.$this->name : 'https://packagist.org/packages/'.$this->name;
    }

    /** The line that installs it. */
    public function getInstallCommand(): string
    {
        return ($this->isNpm() ? 'npm install ' : 'composer require ').$this->name;
    }

    public function getVerification(): Verification
    {
        return $this->verification;
    }

    public function getVerificationNote(): ?string
    {
        return $this->verificationNote;
    }

    public function setVerification(Verification $verification, ?string $note = null): static
    {
        $this->verification = $verification;
        $this->verificationNote = $note;

        return $this;
    }

    public function hasReadme(): bool
    {
        return $this->readme;
    }

    public function setReadme(bool $readme): static
    {
        $this->readme = $readme;

        return $this;
    }

    public function getDocPages(): int
    {
        return $this->docPages;
    }

    public function setDocPages(int $docPages): static
    {
        $this->docPages = max(0, $docPages);

        return $this;
    }

    /** Something to read: a docs/ folder, or at least a README. */
    public function hasManual(): bool
    {
        return $this->docPages > 0 || $this->readme;
    }

    public function isMissing(): bool
    {
        return $this->missing;
    }

    public function setMissing(bool $missing): static
    {
        $this->missing = $missing;

        return $this;
    }

    // What the back office's list prints.

    public function getTierLabel(): string
    {
        return match ($this->tier) {
            Tier::Foundation => 'Cœur',
            Tier::Component => 'Structure préparée',
            Tier::Family => 'Famille',
            Tier::Provider => 'Fournisseur',
            Tier::Bridge => 'Pont',
            Tier::Library => 'Bibliothèque',
        };
    }

    public function getRegistryLabel(): string
    {
        $registry = $this->isNpm() ? 'npm' : 'Packagist';

        return match ($this->published) {
            true => $registry.' : publié',
            false => $registry.' : absent',
            null => $registry.' : non interrogé',
        };
    }

    public function getVerificationLabel(): string
    {
        return match ($this->verification) {
            Verification::Verified => 'Oui',
            Verification::Partial => 'En partie',
            Verification::Unverified => 'Non',
            Verification::Unstated => 'Le README ne le dit pas',
        };
    }

    public function getUsedByLabel(): string
    {
        return implode(', ', $this->usedBy);
    }

    public function getSyncedAt(): \DateTimeImmutable
    {
        return $this->syncedAt;
    }

    public function setSyncedAt(\DateTimeImmutable $syncedAt): static
    {
        $this->syncedAt = $syncedAt;

        return $this;
    }
}
