<?php

namespace App\Catalogue;

use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Yaml\Yaml;

/**
 * The compatibility dashboard's rows: every package of the catalogue with a
 * repository, joined with what app:compat:fetch read from GitHub (its CI jobs,
 * the conformance suite its tests run) and with what its README says of a live
 * check (catalog/<family>.yaml). Nothing here is kept by hand.
 */
class Compatibility
{
    /** @var array<string, mixed>|null */
    private ?array $data = null;

    public function __construct(
        private readonly Catalogue $catalogue,
        #[Autowire('%app.compatibility_file%')] private readonly string $file,
    ) {
    }

    public function generated(): ?string
    {
        $generated = $this->data()['generated'] ?? null;

        return \is_string($generated) ? $generated : null;
    }

    /**
     * The jobs' labels across every package (the dashboard's columns): "PHP 8.4", "Laravel 12"...
     *
     * @return list<string>
     */
    public function labels(): array
    {
        $labels = [];
        foreach ($this->rows() as $row) {
            foreach ($row['jobs'] as $label => $conclusion) {
                $labels[$label] = true;
            }
        }
        $labels = array_keys($labels);
        natsort($labels);

        return array_values($labels);
    }

    /**
     * @return list<array{package: Package, jobs: array<string, string>, runs: list<array{workflow: string, url: string, conclusion: string, at: string, head: string}>, suite: ?string, test: ?string, conformance: string, stale: bool}>
     */
    public function rows(): array
    {
        $packages = (array) ($this->data()['packages'] ?? []);
        $rows = [];
        foreach ($this->catalogue->all() as $name => $package) {
            if (null === $package->getRepository() || $package->isNpm()) {
                continue;
            }
            $entry = \is_array($packages[$name] ?? null) ? $packages[$name] : [];
            $jobs = [];
            $runs = [];
            foreach ((array) ($entry['ci'] ?? []) as $run) {
                if (!\is_array($run)) {
                    continue;
                }
                $runs[] = ['workflow' => (string) ($run['workflow'] ?? ''), 'url' => (string) ($run['url'] ?? ''), 'conclusion' => (string) ($run['conclusion'] ?? ''), 'at' => (string) ($run['at'] ?? ''), 'head' => (string) ($run['head'] ?? '')];
                foreach ((array) ($run['jobs'] ?? []) as $job) {
                    if (\is_array($job) && '' !== ($label = (string) ($job['label'] ?? ''))) {
                        // Two workflows with a job of the same label: the failing one shows.
                        $jobs[$label] = 'success' === ($jobs[$label] ?? 'success') ? (string) ($job['conclusion'] ?? '') : $jobs[$label];
                    }
                }
            }
            $suite = \is_string($entry['conformance']['suite'] ?? null) ? $entry['conformance']['suite'] : null;
            $rows[] = [
                'package' => $package,
                'jobs' => $jobs,
                'runs' => $runs,
                'suite' => $suite,
                'test' => \is_string($entry['conformance']['test'] ?? null) ? $entry['conformance']['test'] : null,
                'conformance' => self::conformance($package, $suite, $runs),
                'stale' => true === ($entry['stale'] ?? false),
            ];
        }

        return $rows;
    }

    /**
     * passes / fails (the suite runs in a CI that passed or failed on the branch's head), unknown (it runs, no CI
     * run read), missing (a provider whose tests do not run it), none (not a provider: no suite to run).
     *
     * @param list<array{conclusion: string}> $runs
     */
    private static function conformance(Package $package, ?string $suite, array $runs): string
    {
        if (null === $suite) {
            return Tier::Provider === $package->getTier() ? 'missing' : 'none';
        }
        if ([] === $runs) {
            return 'unknown';
        }
        foreach ($runs as $run) {
            if ('success' !== $run['conclusion']) {
                return 'fails';
            }
        }

        return 'passes';
    }

    /** @return array<string, mixed> */
    private function data(): array
    {
        return $this->data ??= is_file($this->file) ? (array) Yaml::parseFile($this->file) : [];
    }
}
