<?php

namespace App\Catalogue;

use App\Entity\Package;
use App\Repository\PackageRepository;

/**
 * The install selector's answer: from a framework, a channel and the packages
 * chosen, the composer.json block and the commands that install them.
 *
 * - Each package is required at its minor version when a release exists
 *   (^1.2), else at its branch's development version (1.x-dev) - never `*`.
 * - What the chosen packages require in turn, inside the ecosystem, is
 *   followed: a package not on Packagist gets a `vcs` repository, a
 *   development version anywhere asks `minimum-stability: dev` with
 *   `prefer-stable: true`, and a requirement on a numbered branch
 *   (`3.0.*-dev`) gets the inline alias that satisfies it (`3.x-dev as 3.0.x-dev`).
 * - Families run in plain PHP; glitchr/omnibase and its components are
 *   Symfony bundles today, so they are refused outside Symfony.
 */
class Installer
{
    public const BARE = 'bare';
    public const SYMFONY = 'symfony';
    public const LARAVEL = 'laravel';
    public const FRAMEWORKS = [self::BARE, self::SYMFONY, self::LARAVEL];
    public const CHANNELS = [PackageVersions::STABLE, PackageVersions::DEV];

    /** The Composer plugins the ecosystem's packages bring, which a project has to allow. */
    private const PLUGINS = ['glitchr/base-plugin'];

    public function __construct(
        private readonly PackageRepository $packages,
        private readonly Versions $versions,
        private readonly PackageRepositories $repositories,
    ) {
    }

    /** Whether a package can be installed in that framework today. */
    public static function fits(Package $package, string $framework): bool
    {
        if ($package->isNpm()) {
            return false;
        }

        return self::SYMFONY === $framework || \in_array($package->getTier(), [Tier::Family, Tier::Provider], true);
    }

    /**
     * @param list<string> $names the packages chosen
     */
    public function plan(string $framework, string $channel, array $names): InstallPlan
    {
        $framework = \in_array($framework, self::FRAMEWORKS, true) ? $framework : self::BARE;
        $channel = \in_array($channel, self::CHANNELS, true) ? $channel : PackageVersions::STABLE;

        $chosen = [];
        $refused = [];
        foreach (array_unique($names) as $name) {
            $package = $this->packages->findOneByHandle((string) $name);
            if (null === $package || $package->isNpm()) {
                continue;
            }
            if (!self::fits($package, $framework)) {
                $refused[] = $package->getName();
                continue;
            }
            $chosen[$package->getName()] = $package;
        }
        ksort($chosen);

        // What they require in turn, inside the ecosystem: the packages a vcs repository or a stability may be needed for.
        $closure = $chosen;
        $constraints = [];
        $queue = array_values($chosen);
        while ($package = array_shift($queue)) {
            foreach ($this->requireOf($package) as $required => $constraint) {
                $constraints[$required][] = $constraint;
                if (isset($closure[$required])) {
                    continue;
                }
                $other = $this->packages->findOneByHandle($required);
                if (null !== $other && !$other->isNpm()) {
                    $closure[$required] = $other;
                    $queue[] = $other;
                }
            }
        }
        ksort($closure);

        $require = [];
        foreach ($chosen as $name => $package) {
            $constraint = $this->versions->of($package)->constraint($channel) ?? '*';
            $require[$name] = $this->aliased($constraint, $constraints[$name] ?? []);
        }

        $repositories = [];
        $dev = false;
        foreach ($closure as $name => $package) {
            $own = $require[$name] ?? null;
            $dev = $dev || PackageVersions::isDev($own) || [] !== array_filter($constraints[$name] ?? [], PackageVersions::isDev(...));
            if (true !== $package->isPublished() && null !== $package->getRepositoryUrl()) {
                $repositories[$name] = ['type' => 'vcs', 'url' => $package->getRepositoryUrl()];
            }
        }

        $plugins = array_values(array_filter(self::PLUGINS, fn (string $plugin): bool => (bool) array_filter($closure, fn (Package $p): bool => \array_key_exists($plugin, $this->requireOf($p)))));

        return new InstallPlan($framework, $channel, $chosen, $require, $repositories, $dev, $plugins, $refused);
    }

    /** @var array<string, array<string, string>> what each composer.json requires, read once per request */
    private array $read = [];

    /** @return array<string, string> what a package's composer.json requires, by name */
    private function requireOf(Package $package): array
    {
        $file = $this->repositories->directoryOf($package).'/composer.json';
        if (!isset($this->read[$file])) {
            $json = is_file($file) ? json_decode((string) file_get_contents($file), true) : null;
            $this->read[$file] = \is_array($json['require'] ?? null) ? array_filter($json['require'], 'is_string') : [];
        }

        return $this->read[$file];
    }

    /**
     * A branch's development version, given the alias other packages ask for:
     * glitchr/omnibase is 3.x-dev, omnibase/admin requires 3.0.*-dev - "3.x-dev as 3.0.x-dev".
     *
     * @param list<string> $asked
     */
    private function aliased(string $constraint, array $asked): string
    {
        if (1 !== preg_match('/^(\d+)\.x-dev$/', $constraint, $own)) {
            return $constraint;
        }
        foreach ($asked as $other) {
            if (1 === preg_match('/^(\d+)\.(\d+)\.\*-dev$/', $other, $m) && $m[1] === $own[1]) {
                return $constraint.' as '.$m[1].'.'.$m[2].'.x-dev';
            }
        }

        return $constraint;
    }
}
