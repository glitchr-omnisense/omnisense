<?php

namespace App\Catalogue;

use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Yaml\Yaml;

/**
 * Writes the catalogue's files from the repositories (RepositoryScanner):
 * catalog/<family>.yaml, one per family, and config/documentation.yaml, the
 * commit each package's docs/ is read at.
 *
 * What a maintainer wrote by hand is kept: a family's phrase and text, and
 * a documentation version already pinned (its ref moves only with $pin, an
 * explicit and reviewed change - a moving branch never rewrites published
 * documentation by itself).
 */
class CatalogueImporter
{
    public function __construct(
        private readonly RepositoryScanner $scanner,
        #[Autowire('%app.catalog_dir%')] private readonly string $catalogDir,
        #[Autowire('%app.documentation_file%')] private readonly string $documentationFile,
        #[Autowire('%kernel.project_dir%/translations')] private readonly string $translationsDir,
    ) {
    }

    /**
     * @return array{families: int, packages: int, documented: int, pinned: int, skipped: list<string>}
     */
    public function import(bool $pin = false): array
    {
        $scanned = $this->scanner->scan();
        $report = ['families' => 0, 'packages' => 0, 'documented' => 0, 'pinned' => 0, 'skipped' => []];

        $byFamily = [];
        foreach ($scanned as $name => $package) {
            // Only what anyone can read: a package whose repository is not public on GitHub stays out.
            if (null === $package->repository) {
                $report['skipped'][] = $name;
                continue;
            }
            $byFamily[self::fileOf($package)][$name] = $package;
        }
        ksort($byFamily);

        $documentation = is_file($this->documentationFile) ? (array) (Yaml::parseFile($this->documentationFile)['packages'] ?? []) : [];
        $documented = [];

        if (!is_dir($this->catalogDir)) {
            mkdir($this->catalogDir, 0o775, true);
        }
        foreach ($byFamily as $family => $packages) {
            $file = $this->catalogDir.'/'.$family.'.yaml';
            $existing = is_file($file) ? (array) Yaml::parseFile($file) : [];
            $entries = [];
            foreach ($packages as $name => $package) {
                [$verification, $note] = null !== $package->readme ? Verification::read($package->readme) : [Verification::Unstated, null];
                $entry = array_filter([
                    'kind' => $package->tier->kind(),
                    'description' => $package->description,
                    'license' => $package->license,
                    'keywords' => $package->keywords ?: null,
                    'repository' => $package->repository,
                    'branch' => $package->branch,
                    'requires' => array_values(array_filter($package->requires, static fn (string $r): bool => isset($scanned[$r]))) ?: null,
                    'verification' => $verification->value,
                    'verification_note' => $note,
                    'docs' => $package->docPages > 0 && null !== $package->head ? true : null,
                ], static fn ($v): bool => null !== $v);
                $entries[$name] = $entry;

                if ($package->docPages > 0 && null !== $package->head && null !== $package->branch) {
                    $key = Package::keyOf($name);
                    $documented[$key] = self::pinned($documentation[$key] ?? null, $name, $package, $pin, $report);
                }
            }

            $data = [
                'family' => $family,
                'phrase' => $existing['phrase'] ?? $this->translated($family, 'phrase'),
                'text' => $existing['text'] ?? $this->translated($family, 'text'),
                'packages' => $entries,
            ];
            file_put_contents($file, self::header($family).Yaml::dump(array_filter($data, static fn ($v): bool => [] !== $v), 6, 2, Yaml::DUMP_MULTI_LINE_LITERAL_BLOCK));
            ++$report['families'];
            $report['packages'] += \count($entries);
        }

        ksort($documented);
        $report['documented'] = \count($documented);
        file_put_contents($this->documentationFile, <<<'YAML'
            # The documentation each package publishes in its own repository (its docs/
            # folder), read by the website at an immutable commit. Each package has its
            # own version series; a series names the branch it follows and the commit it
            # is read at. No package has a release tag yet: every ref is the head of a
            # published branch (origin/<branch>) when it was pinned - `pinned: branch-head`
            # (a series read at a release tag would say `pinned: tag`). A package with
            # several maintained series (release branches such as 3.x and 4.x, each with a
            # docs/ folder) lists each; `default` is the current one, the others are shown
            # with a notice and kept out of search engines' indexes.
            #
            #   bin/console app:catalog:import          new packages and series, pinned refs kept
            #   bin/console app:catalog:import --pin    every series moved to its branch's head
            #   bin/console app:docs:fetch              the docs/ folders at these refs, into var/docs
            #
            # A series kept for history keeps its ref: add it by hand, with its tag or commit.

            YAML.Yaml::dump(['packages' => $documented], 6, 2));

        return $report;
    }

    /**
     * @param array<string, mixed>|null $existing
     * @param array<string, mixed>      $report
     *
     * @return array<string, mixed>
     */
    private static function pinned(?array $existing, string $name, ScannedPackage $package, bool $pin, array &$report): array
    {
        $versions = (array) ($existing['versions'] ?? []);
        $series = $package->branch;
        $current = $versions[$series] ?? null;
        if (null === $current || ($pin && ($current['ref'] ?? null) !== $package->head)) {
            $versions[$series] = ['branch' => $series, 'ref' => $package->head, 'pinned' => 'branch-head', 'path' => 'docs/'];
            ++$report['pinned'];
        }

        return [
            'package' => $name,
            'repository' => $package->repository,
            'default' => $existing['default'] ?? $series,
            'versions' => $versions,
        ];
    }

    /** The file a package is listed in: its family's, the bridges', the libraries'. */
    private static function fileOf(ScannedPackage $package): string
    {
        return match ($package->tier) {
            Tier::Bridge => 'omnibridge',
            Tier::Library => 'libraries',
            default => $package->family ?? 'other',
        };
    }

    /** @return array<string, string> locale => the sentence the translations had for the family */
    private function translated(string $family, string $key): array
    {
        $out = [];
        foreach (['en', 'fr'] as $locale) {
            $file = $this->translationsDir.'/messages+intl-icu.'.$locale.'.yaml';
            $value = is_file($file) ? (Yaml::parseFile($file)['families'][$family][$key] ?? null) : null;
            if (\is_string($value)) {
                $out[$locale] = $value;
            }
        }

        return $out;
    }

    private static function header(string $family): string
    {
        return <<<YAML
            # The {$family} entry of the Omniscient catalogue. Written by `bin/console
            # app:catalog:import` from the packages' repositories; the phrase and the
            # text are the site's own, edited here. Each package: its kind, what its
            # composer.json says, its GitHub repository and development branch, whether
            # its README says it was verified against the real service, whether it has
            # a docs/ folder (see config/documentation.yaml).

            YAML;
    }
}
