<?php

namespace App\Catalogue\Api;

use App\Catalogue\Catalogue;
use App\Catalogue\RepositoryScanner;
use App\Catalogue\Tier;
use Omnisense\Contracts\Policy\PolicyExport;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Process\Process;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Writes config/policies.json: the usage policy each provider of the
 * catalogue declares - its policy.json, named in its composer.json
 * (`extra.omnisense.policy`) - read at its published head, never at an
 * unpushed commit or a working copy's changes, and exported by
 * omnisense/contracts' PolicyExport, which checks each against the schema.
 *
 * From GitHub (the build's CI step): the branch's head by `git ls-remote`,
 * then the two files at that commit from raw.githubusercontent.com. With
 * $local (a maintainer's machine, no network): the working copies under
 * OMNI_REPOS, each read at refs/remotes/origin/<branch> by `git show` - what
 * the remote was last seen at; a folder that is no checkout (the tests'
 * fixtures) is read as it is.
 *
 * A policy that does not validate fails the import and nothing is written.
 * A package that could not be read (GitHub down, a private repository)
 * keeps the entry the file had, and says so.
 */
class PolicyImporter
{
    private const RAW = 'https://raw.githubusercontent.com/%s/%s/%s';

    public function __construct(
        private readonly Catalogue $catalogue,
        private readonly RepositoryScanner $scanner,
        private readonly HttpClientInterface $http,
        #[Autowire('%app.policies_file%')] private readonly string $file,
    ) {
    }

    /**
     * @return array{written: bool, packages: int, policies: int, errors: list<string>, kept: list<string>}
     */
    public function import(bool $local = false): array
    {
        $providers = [];
        foreach ($this->catalogue->findByTier(Tier::Provider) as $package) {
            if (null !== $package->getRepository() && null !== $package->getBranch()) {
                $providers[$package->getName()] = [$package->getRepository(), $package->getBranch()];
            }
        }
        ksort($providers);

        $sources = $local ? $this->readLocal(array_keys($providers)) : $this->readRemote($providers);
        $report = ['written' => false, 'packages' => \count($providers), 'policies' => 0, 'errors' => [], 'kept' => []];

        $fs = new Filesystem();
        $work = sys_get_temp_dir().'/omnisense-policies-'.bin2hex(random_bytes(6));
        $folders = [];
        foreach ($sources as $name => $source) {
            if (null === $source) {
                continue;
            }
            $folder = $work.'/'.str_replace('/', '--', $name);
            foreach ($source['files'] as $path => $contents) {
                $fs->dumpFile($folder.'/'.$path, $contents);
            }
            $folders[$name] = $folder;
        }
        $export = (new PolicyExport())->folders(...array_values($folders));
        $fs->remove($work);

        $entries = [];
        foreach ($export->entries() as $entry) {
            if (isset($entry['error'])) {
                $report['errors'][] = $entry['package'].' ('.$entry['file'].'): '.$entry['error'];
                continue;
            }
            $entries[] = ['package' => $entry['package'], 'version' => $providers[$entry['package']][1].'-dev', 'ref' => $sources[$entry['package']]['ref'] ?? null, 'file' => $entry['file'], 'policy' => $entry['policy']];
        }
        // What could not be read keeps what the file had.
        foreach ($this->previous() as $entry) {
            if (\array_key_exists($entry['package'] ?? '', $sources) && null === $sources[$entry['package']]) {
                $entries[] = $entry;
                $report['kept'][] = $entry['package'];
            }
        }
        if ([] !== $report['errors']) {
            return $report;
        }
        usort($entries, static fn (array $a, array $b): int => [$a['package'], $a['file']] <=> [$b['package'], $b['file']]);
        $report['policies'] = \count($entries);

        $fs->dumpFile($this->file, json_encode([
            'schema' => 'https://github.com/glitchr-omnisense/contracts/blob/1.x/resources/usage-policy.schema.json',
            'about' => 'Written by `bin/console app:policies:import` (omnisense/contracts\' PolicyExport): each provider\'s policy.json at its published head (ref). Edit the package\'s file, never this one.',
            'policies' => $entries,
        ], \JSON_PRETTY_PRINT | \JSON_UNESCAPED_SLASHES | \JSON_UNESCAPED_UNICODE | \JSON_THROW_ON_ERROR)."\n");
        $report['written'] = true;

        return $report;
    }

    /**
     * @param list<string> $names
     *
     * @return array<string, array{ref: ?string, files: array<string, string>}|null>
     */
    private function readLocal(array $names): array
    {
        $scanned = $this->scanner->scan();
        $root = $this->scanner->getReposDir();
        $out = [];
        foreach ($names as $name) {
            $package = $scanned[$name] ?? null;
            if (null === $package) {
                $out[$name] = null;
                continue;
            }
            $dir = $root.'/'.$package->path;
            $read = null === $package->head
                ? static fn (string $path): ?string => is_file($dir.'/'.$path) ? (string) file_get_contents($dir.'/'.$path) : null
                : static function (string $path) use ($dir, $package): ?string {
                    $process = new Process(['git', '-c', 'safe.directory=*', '-C', $dir, 'show', $package->head.':'.$path]);
                    $process->run();

                    return $process->isSuccessful() ? $process->getOutput() : null;
                };
            $composer = $read('composer.json');
            if (null === $composer) {
                $out[$name] = null;
                continue;
            }
            $files = ['composer.json' => $composer];
            foreach (self::policyFiles($composer) as $path) {
                $contents = $read($path);
                if (null !== $contents) {
                    $files[$path] = $contents;
                }
            }
            $out[$name] = ['ref' => $package->head, 'files' => $files];
        }

        return $out;
    }

    /**
     * @param array<string, array{0: string, 1: string}> $providers name => [repository, branch]
     *
     * @return array<string, array{ref: ?string, files: array<string, string>}|null>
     */
    private function readRemote(array $providers): array
    {
        // The heads, a few `git ls-remote` at a time (no API quota).
        $heads = [];
        foreach (array_chunk($providers, 12, true) as $chunk) {
            $running = [];
            foreach ($chunk as $name => [$repository, $branch]) {
                $process = new Process(['git', 'ls-remote', 'https://github.com/'.$repository.'.git', 'refs/heads/'.$branch], null, ['GIT_TERMINAL_PROMPT' => '0']);
                $process->setTimeout(30);
                $process->start();
                $running[$name] = $process;
            }
            foreach ($running as $name => $process) {
                $process->wait();
                $sha = $process->isSuccessful() ? strtok(trim($process->getOutput()), "\t") : false;
                $heads[$name] = \is_string($sha) && 1 === preg_match('/^[0-9a-f]{40}$/', $sha) ? $sha : null;
            }
        }

        $get = function (array $wanted): array {
            $responses = [];
            foreach ($wanted as $key => $url) {
                $responses[$key] = $this->http->request('GET', $url, ['timeout' => 20, 'headers' => ['User-Agent' => 'omnisense-website']]);
            }
            $out = [];
            foreach ($responses as $key => $response) {
                try {
                    $out[$key] = 200 === $response->getStatusCode() ? $response->getContent() : null;
                } catch (\Throwable) {
                    $out[$key] = null;
                }
            }

            return $out;
        };

        $composers = $get(array_map(
            static fn (string $name): string => \sprintf(self::RAW, $providers[$name][0], $heads[$name], 'composer.json'),
            array_combine($n = array_keys(array_filter($heads)), $n) ?: [],
        ));
        $wanted = [];
        foreach ($composers as $name => $composer) {
            foreach (null === $composer ? [] : self::policyFiles($composer) as $path) {
                $wanted[$name."\0".$path] = \sprintf(self::RAW, $providers[$name][0], $heads[$name], $path);
            }
        }
        $policies = $get($wanted);

        $out = [];
        foreach (array_keys($providers) as $name) {
            $composer = $composers[$name] ?? null;
            if (null === $composer) {
                $out[$name] = null;
                continue;
            }
            $files = ['composer.json' => $composer];
            foreach (self::policyFiles($composer) as $path) {
                if (null === ($policies[$name."\0".$path] ?? null)) {
                    // Named and not there: the export lists the missing file as an error.
                    continue;
                }
                $files[$path] = $policies[$name."\0".$path];
            }
            $out[$name] = ['ref' => $heads[$name], 'files' => $files];
        }

        return $out;
    }

    /** @return list<string> the policy files a composer.json names (extra.omnisense.policy, or .policies) */
    private static function policyFiles(string $composer): array
    {
        $data = json_decode($composer, true);
        $extra = \is_array($data) && \is_array($data['extra']['omnisense'] ?? null) ? $data['extra']['omnisense'] : [];
        $files = $extra['policy'] ?? $extra['policies'] ?? [];
        $files = \is_string($files) ? [$files] : (\is_array($files) ? $files : []);

        return array_values(array_filter($files, static fn ($f): bool => \is_string($f) && '' !== $f && !str_contains($f, '..')));
    }

    /** @return list<array<string, mixed>> */
    private function previous(): array
    {
        $data = is_file($this->file) ? json_decode((string) file_get_contents($this->file), true) : null;

        return \is_array($data) && \is_array($data['policies'] ?? null) ? array_values(array_filter($data['policies'], 'is_array')) : [];
    }
}
