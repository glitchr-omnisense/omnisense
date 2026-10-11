<?php

namespace App\Catalogue;

/**
 * What a package's repository says of its versions: its tags (the releases,
 * newest first) and its branches (what each holds: its last commits), and
 * from them the Composer constraint that takes it - at the minor version when
 * a release exists (^1.2), else the branch's development version (1.x-dev).
 */
final class PackageVersions
{
    public const STABLE = 'stable';
    public const DEV = 'dev';

    /**
     * @param list<array{name: string, version: string, sha: string, date: ?\DateTimeImmutable, stable: bool}> $tags
     * @param list<array{name: string, version: string, sha: string, date: ?\DateTimeImmutable, default: bool, commits: list<array{sha: string, subject: string, date: \DateTimeImmutable}>}> $branches
     */
    public function __construct(
        public readonly array $tags = [],
        public readonly array $branches = [],
        public readonly bool $read = false,
        public readonly ?string $publishedVersion = null,
    ) {
    }

    /** The newest release that is not a pre-release, or null. */
    public function latestStable(): ?array
    {
        foreach ($this->tags as $tag) {
            if ($tag['stable']) {
                return $tag;
            }
        }

        return null;
    }

    /** The branch a package is developed on: the repository's checked-out one, else the first. */
    public function defaultBranch(): ?array
    {
        foreach ($this->branches as $branch) {
            if ($branch['default']) {
                return $branch;
            }
        }

        return $this->branches[0] ?? null;
    }

    /**
     * The constraint a composer.json requires it with: on the stable channel
     * the latest release at its minor version, from a tag of the repository or
     * from the registry; otherwise - or when nothing is released - the
     * development version of its branch. Null when neither is known.
     */
    public function constraint(string $channel = self::STABLE): ?string
    {
        if (self::STABLE === $channel) {
            $stable = $this->latestStable()['version'] ?? (null !== $this->publishedVersion && self::isStable($this->publishedVersion) ? self::normalize($this->publishedVersion) : null);
            if (null !== $stable) {
                return self::caret($stable);
            }
        }

        return $this->defaultBranch()['version'] ?? null;
    }

    /** Whether a constraint takes a development version (and so asks minimum-stability dev of the project). */
    public static function isDev(?string $constraint): bool
    {
        return null !== $constraint && (str_starts_with($constraint, 'dev-') || str_ends_with($constraint, '-dev'));
    }

    /** "v1.4.2" -> "1.4.2"; null for what is no version number. */
    public static function normalize(string $tag): ?string
    {
        return 1 === preg_match('/^v?(\d+\.\d+(?:\.\d+)?(?:-[0-9A-Za-z.]+)?)$/', $tag, $m) ? $m[1] : null;
    }

    public static function isStable(string $version): bool
    {
        $version = self::normalize($version);

        return null !== $version && !str_contains($version, '-');
    }

    /** "1.4.2" -> "^1.4": the minor version, which a later 1.x keeps compatible. */
    public static function caret(string $version): string
    {
        $parts = explode('.', self::normalize($version) ?? $version);

        return '^'.$parts[0].'.'.($parts[1] ?? '0');
    }

    /** The version Composer gives a branch: "1.x" and "2.0" are numbered (1.x-dev, 2.0.x-dev), any other is dev-<name>. */
    public static function branchVersion(string $branch): string
    {
        if (1 === preg_match('/^v?(\d+)(\.(\d+|x))?(\.x)?$/i', $branch, $m)) {
            $version = $m[1].(isset($m[2]) && '' !== $m[2] ? $m[2] : '');

            return (str_ends_with($version, '.x') ? $version : $version.'.x').'-dev';
        }

        return 'dev-'.$branch;
    }
}
