<?php

namespace App\Catalogue;

use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Yaml\Yaml;
use Symfony\Contracts\HttpClient\Exception\ExceptionInterface as HttpException;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Writes the compatibility dashboard's data file from GitHub, at build time
 * (`app:compat:fetch`, before the static export): for every package of the
 * catalogue with a repository, the last finished run of each workflow on its
 * development branch and its jobs (the PHP version, the framework each tests),
 * and whether its tests run its family's conformance suite - a test class
 * importing `<Core>\Testing\<Something>ContractTests`.
 *
 * Never on a page view: the pages read the file. A package GitHub did not
 * answer for keeps what the file held (marked stale), so a rate limit never
 * blanks the dashboard.
 */
class CompatibilityFetcher
{
    private const API = 'https://api.github.com';
    private const RAW = 'https://raw.githubusercontent.com';
    /** The workflows that publish rather than test. */
    private const NOT_TESTS = '/^(release|website|pages build and deployment|image|dependabot.*)$/i';
    private const SUITE = '/^use\s+((?:[A-Z]\w*\\\\)+Testing\\\\\w+ContractTests)\s*;/m';

    public function __construct(
        private readonly HttpClientInterface $http,
        private readonly Catalogue $catalogue,
        #[Autowire('%app.compatibility_file%')] private readonly string $file,
        #[Autowire('%env(default::GITHUB_TOKEN)%')] private readonly ?string $token = null,
    ) {
    }

    /**
     * @return array{packages: int, fetched: int, stale: list<string>, suites: int}
     */
    public function fetch(?\DateTimeImmutable $now = null): array
    {
        $previous = is_file($this->file) ? (array) (Yaml::parseFile($this->file)['packages'] ?? []) : [];
        $report = ['packages' => 0, 'fetched' => 0, 'stale' => [], 'suites' => 0];
        $packages = [];
        foreach ($this->catalogue->all() as $name => $package) {
            $repository = $package->getRepository();
            $branch = $package->getBranch();
            if (null === $repository || null === $branch || $package->isNpm()) {
                continue;
            }
            ++$report['packages'];
            try {
                $entry = $this->package($repository, $branch);
                ++$report['fetched'];
            } catch (HttpException|\UnexpectedValueException $e) {
                $entry = \is_array($previous[$name] ?? null) ? ['stale' => true] + $previous[$name] : ['repository' => $repository, 'branch' => $branch, 'error' => $e->getMessage()];
                $report['stale'][] = $name;
            }
            if (null !== ($entry['conformance']['suite'] ?? null)) {
                ++$report['suites'];
            }
            $packages[$name] = $entry;
        }
        ksort($packages);

        $dir = \dirname($this->file);
        if (!is_dir($dir)) {
            mkdir($dir, 0o775, true);
        }
        file_put_contents($this->file, <<<'YAML'
            # The compatibility dashboard's data (/compatibility), written by
            # `bin/console app:compat:fetch` from GitHub's Actions and Git APIs at build
            # time: each package's last finished run of each testing workflow on its
            # development branch, its jobs, and the conformance suite its tests run.
            # Generated - not edited by hand. The README's "Verified, and not" is read by
            # app:catalog:import into catalog/<family>.yaml.

            YAML.Yaml::dump(['generated' => ($now ?? new \DateTimeImmutable())->format(\DATE_ATOM), 'packages' => $packages], 6, 2));

        return $report;
    }

    /** @return array<string, mixed> */
    private function package(string $repository, string $branch): array
    {
        $runs = $this->json(\sprintf('%s/repos/%s/actions/runs?branch=%s&status=completed&per_page=30&exclude_pull_requests=true', self::API, $repository, rawurlencode($branch)));
        $latest = [];
        foreach ((array) ($runs['workflow_runs'] ?? []) as $run) {
            if (!\is_array($run) || !\in_array($run['event'] ?? '', ['push', 'workflow_dispatch', 'schedule'], true)) {
                continue;
            }
            $workflow = (string) ($run['name'] ?? '');
            if ('' === $workflow || isset($latest[$workflow]) || preg_match(self::NOT_TESTS, $workflow)) {
                continue;
            }
            $latest[$workflow] = $run;
        }
        $ci = [];
        foreach ($latest as $workflow => $run) {
            $jobs = $this->json(\sprintf('%s/repos/%s/actions/runs/%d/jobs?per_page=50', self::API, $repository, (int) $run['id']));
            $ci[] = [
                'workflow' => $workflow,
                'url' => (string) ($run['html_url'] ?? ''),
                'head' => (string) ($run['head_sha'] ?? ''),
                'at' => (string) ($run['updated_at'] ?? ''),
                'conclusion' => (string) ($run['conclusion'] ?? ''),
                'jobs' => array_values(array_map(static fn (array $job): array => [
                    'label' => self::label((string) ($job['name'] ?? '')),
                    'conclusion' => (string) ($job['conclusion'] ?? ''),
                ], array_filter((array) ($jobs['jobs'] ?? []), 'is_array'))),
            ];
        }

        return [
            'repository' => $repository,
            'branch' => $branch,
            'ci' => $ci,
            'conformance' => $this->conformance($repository, $branch),
        ];
    }

    /**
     * The conformance suite a test class of the package imports, read at the branch's head.
     *
     * @return array{suite: ?string, test: ?string}
     */
    private function conformance(string $repository, string $branch): array
    {
        $tree = $this->json(\sprintf('%s/repos/%s/git/trees/%s?recursive=1', self::API, $repository, rawurlencode($branch)));
        $sha = (string) ($tree['sha'] ?? $branch);
        $tests = [];
        foreach ((array) ($tree['tree'] ?? []) as $node) {
            $path = \is_array($node) ? (string) ($node['path'] ?? '') : '';
            if (preg_match('#^(?:Tests|tests)/(?:[^/]+/)?[^/]*Test\.php$#', $path)) {
                $tests[] = $path;
            }
        }
        // The suites' own names first (*ContractTest.php), then the other test classes, a few at most.
        usort($tests, static fn (string $a, string $b): int => [!str_contains($a, 'Contract'), $a] <=> [!str_contains($b, 'Contract'), $b]);
        foreach (\array_slice($tests, 0, 12) as $path) {
            $response = $this->http->request('GET', \sprintf('%s/%s/%s/%s', self::RAW, $repository, $sha, $path));
            if (200 !== $response->getStatusCode()) {
                continue;
            }
            $source = $response->getContent();
            if (preg_match(self::SUITE, $source, $match) && preg_match('/^\s*(?:final\s+|abstract\s+)?class\s/m', $source)) {
                return ['suite' => $match[1], 'test' => $path];
            }
        }

        return ['suite' => null, 'test' => null];
    }

    /** "package / PHP 8.4" (a reusable workflow's job) reads "PHP 8.4". */
    private static function label(string $job): string
    {
        $parts = explode(' / ', $job);

        return trim((string) end($parts));
    }

    /** @return array<mixed> */
    private function json(string $url): array
    {
        $headers = ['Accept' => 'application/vnd.github+json', 'X-GitHub-Api-Version' => '2022-11-28', 'User-Agent' => 'omniscient-website'];
        if (null !== $this->token && '' !== $this->token) {
            $headers['Authorization'] = 'Bearer '.$this->token;
        }
        $response = $this->http->request('GET', $url, ['headers' => $headers]);
        if (200 !== $response->getStatusCode()) {
            throw new \UnexpectedValueException(\sprintf('GitHub answered %d for %s.', $response->getStatusCode(), $url));
        }

        return $response->toArray();
    }
}
