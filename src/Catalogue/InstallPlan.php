<?php

namespace App\Catalogue;

use App\Entity\Package;

/**
 * What the install selector hands back: the require block, the repositories
 * and the stability a project needs, as a composer.json fragment and as the
 * commands that write it.
 */
final class InstallPlan
{
    /**
     * @param array<string, Package>                          $packages     the packages chosen, by name
     * @param array<string, string>                           $require      their constraints, by name
     * @param array<string, array{type: string, url: string}> $repositories the vcs repositories of what is not on Packagist, by package
     * @param list<string>                                    $plugins      the Composer plugins to allow
     * @param list<string>                                    $refused      chosen, but not for this framework (yet)
     * @param list<class-string|string>                       $bundles      the Symfony bundles to declare by hand (no Flex recipe)
     */
    public function __construct(
        public readonly string $framework,
        public readonly string $channel,
        public readonly array $packages,
        public readonly array $require,
        public readonly array $repositories,
        public readonly bool $dev,
        public readonly array $plugins,
        public readonly array $refused,
        public readonly array $bundles = [],
    ) {
    }

    public function isEmpty(): bool
    {
        return [] === $this->require;
    }

    /** The composer.json fragment: repositories, require, stability, the plugins to allow. */
    public function composerJson(): string
    {
        $json = [];
        if ($this->repositories) {
            $json['repositories'] = array_values($this->repositories);
        }
        $json['require'] = $this->require;
        if ($this->dev) {
            $json['minimum-stability'] = 'dev';
            $json['prefer-stable'] = true;
        }
        if ($this->plugins) {
            $json['config'] = ['allow-plugins' => array_fill_keys($this->plugins, true)];
        }

        return json_encode($json, \JSON_PRETTY_PRINT | \JSON_UNESCAPED_SLASHES | \JSON_UNESCAPED_UNICODE);
    }

    /** @return list<string> the commands, in the order they are typed */
    public function commands(): array
    {
        $commands = [];
        foreach ($this->repositories as $name => $repository) {
            $commands[] = sprintf('composer config repositories.%s %s %s', str_replace('/', '-', $name), $repository['type'], $repository['url']);
        }
        if ($this->dev) {
            $commands[] = 'composer config minimum-stability dev';
            $commands[] = 'composer config prefer-stable true';
        }
        foreach ($this->plugins as $plugin) {
            $commands[] = sprintf('composer config allow-plugins.%s true', $plugin);
        }
        if ($this->require) {
            $commands[] = 'composer require '.implode(' ', array_map(
                static fn (string $name, string $constraint): string => str_contains($constraint, ' ') ? sprintf('"%s:%s"', $name, $constraint) : $name.':'.$constraint,
                array_keys($this->require),
                $this->require,
            ));
        }

        return $commands;
    }

    /** The lines config/bundles.php needs, when Flex has no recipe for the bundles. */
    public function bundlesPhp(): string
    {
        return implode("\n", array_map(static fn (string $bundle): string => sprintf("%s::class => ['all' => true],", $bundle), $this->bundles));
    }

    /** Whether a family is installed: its registry is built by hand (plain PHP) or its bundle declared (Symfony). */
    public function hasFamilies(): bool
    {
        return (bool) array_filter($this->packages, static fn (Package $p): bool => \in_array($p->getTier(), [Tier::Family, Tier::Provider], true));
    }
}
