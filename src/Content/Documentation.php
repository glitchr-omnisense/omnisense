<?php

namespace App\Content;

use Omnidocs\Linking\RepositoryLinker;
use Omnidocs\Markdown\MarkdownRenderer;
use Omnidocs\Markdown\MarkdownSource;
use Omnidocs\Model\Node;
use Omnidocs\Model\Version;
use Omnidocs\OnePage;
use Omnidocs\Php\PhpSource;
use Symfony\Component\DependencyInjection\Attribute\Autoconfigure;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Yaml\Yaml;

/**
 * Each package's own documentation, as the website publishes it: the
 * manuals config/documentation.yaml declares, read from the docs/ folders
 * `app:docs:fetch` wrote under var/docs/<key>/<ref>/ - never from a moving
 * branch, never from the network at request time. A series that was not
 * fetched has no pages (the build reports it).
 *
 * Public: static.php reads it from the container for the routes' parameters.
 */
#[Autoconfigure(public: true)]
class Documentation
{
    /** @var array<string, Manual>|null */
    private ?array $manuals = null;

    /** @var array<string, Version|null> */
    private array $versions = [];

    /** @var array<string, OnePage|null> */
    private array $onePages = [];

    /** @var array<string, Version|null> */
    private array $apis = [];

    /** @var array<string, mixed>|null */
    private ?array $lock = null;

    public function __construct(
        private readonly MarkdownRenderer $markdown,
        #[Autowire('%app.documentation_file%')] private readonly string $file,
        #[Autowire('%app.docs_dir%')] private readonly string $dir,
    ) {
    }

    /** @return array<string, Manual> by key */
    public function manuals(): array
    {
        if (null === $this->manuals) {
            $this->manuals = [];
            $data = is_file($this->file) ? (array) (Yaml::parseFile($this->file)['packages'] ?? []) : [];
            foreach ($data as $key => $entry) {
                $versions = [];
                foreach ((array) ($entry['versions'] ?? []) as $series => $source) {
                    $versions[(string) $series] = [
                        'branch' => isset($source['branch']) ? (string) $source['branch'] : null,
                        'ref' => (string) ($source['ref'] ?? throw new \UnexpectedValueException(sprintf('config/documentation.yaml: %s %s has no ref.', $key, $series))),
                        // How the ref was chosen: the head of the branch when it was pinned, or a release tag.
                        'pinned' => isset($source['pinned']) ? (string) $source['pinned'] : null,
                        'path' => trim((string) ($source['path'] ?? 'docs/'), '/'),
                        'eol' => (bool) ($source['eol'] ?? false),
                    ];
                }
                $this->manuals[(string) $key] = new Manual((string) $key, (string) $entry['package'], (string) $entry['repository'], (string) ($entry['default'] ?? array_key_first($versions)), $versions, isset($entry['website']) ? (string) $entry['website'] : null);
            }
            ksort($this->manuals);
        }

        return $this->manuals;
    }

    public function manual(string $key): ?Manual
    {
        return $this->manuals()[$key] ?? null;
    }

    public function manualOf(string $package): ?Manual
    {
        foreach ($this->manuals() as $manual) {
            if ($manual->package === $package) {
                return $manual;
            }
        }

        return null;
    }

    /** Where a series' docs/ folder was fetched; null when it was not. */
    public function folder(Manual $manual, string $series): ?string
    {
        $ref = $manual->versions[$series]['ref'] ?? null;
        $folder = null !== $ref ? rtrim($this->dir, '/').'/'.$manual->key.'/'.$ref : null;

        return null !== $folder && is_dir($folder) ? $folder : null;
    }

    /** The commit a series was read at, as app:docs:fetch resolved it. */
    public function commit(Manual $manual, string $series): ?string
    {
        if (null === $this->lock) {
            $lock = rtrim($this->dir, '/').'/manifest.json';
            $this->lock = is_file($lock) ? (array) json_decode((string) file_get_contents($lock), true) : [];
        }

        return $this->lock['manuals'][$manual->key][$series]['commit'] ?? null;
    }

    /**
     * A series read through omnidocs: its docs/ folder as a Markdown source (folders are sections,
     * files pages, ordered by their front matter or a number before their name; index.md or README.md
     * the home), at the commit it was fetched at.
     */
    public function version(Manual $manual, string $series): ?Version
    {
        $id = $manual->key.'@'.$series;
        if (!\array_key_exists($id, $this->versions)) {
            $folder = $this->folder($manual, $series);
            $source = $manual->versions[$series] ?? null;
            $this->versions[$id] = null !== $folder && null !== $source
                ? new Version($series, new MarkdownSource($folder, '', repository: $source['path'], renderer: $this->markdown), $source['branch'], $this->commit($manual, $series) ?? $source['ref'], $source['eol'])
                : null;
        }

        return $this->versions[$id];
    }

    /** @return list<Node> a series' contents: its folders as sections, its files as pages (the home apart) */
    public function tree(Manual $manual, string $series): array
    {
        return $this->version($manual, $series)?->tree() ?? [];
    }

    /** @return list<Node> every page of the series, the home first, then the tree depth first */
    public function pages(Manual $manual, string $series): array
    {
        return $this->version($manual, $series)?->pages() ?? [];
    }

    public function home(Manual $manual, string $series): ?Node
    {
        return $this->version($manual, $series)?->home();
    }

    public function page(Manual $manual, string $series, string $path): ?Node
    {
        return $this->version($manual, $series)?->node($path);
    }

    /**
     * A series read as one page (omnidocs' OnePage): every page in reading order, at $url - its links
     * between pages to their parts, its links to other files of the repository and its pictures to
     * GitHub at the commit read, never to a moving branch.
     */
    public function onePage(Manual $manual, string $series, string $url): ?OnePage
    {
        $id = $manual->key.'@'.$series.'@'.$url;
        if (!\array_key_exists($id, $this->onePages)) {
            $version = $this->version($manual, $series);
            $this->onePages[$id] = null !== $version ? new OnePage($version, $this->linker($manual, $series, $url), $url) : null;
        }

        return $this->onePages[$id];
    }

    /** The links of a series' pages: GitHub at the commit read, for what is not a page. */
    public function linker(Manual $manual, string $series, string $url): RepositoryLinker
    {
        $commit = $this->commit($manual, $series) ?? $manual->versions[$series]['ref'] ?? null;

        return new RepositoryLinker(static fn (string $path, ?string $fragment): string => $url.(null !== $fragment ? '#'.$fragment : ''), $manual->repository, $commit);
    }

    /**
     * A PHP package's API reference (omnidocs/php), read from the PHP files app:docs:fetch kept beside
     * the docs/ folder (<ref>/.php); null for a JavaScript library or a series without them.
     */
    public function api(Manual $manual, string $series): ?Version
    {
        $id = $manual->key.'@'.$series;
        if (!\array_key_exists($id, $this->apis)) {
            $folder = $this->folder($manual, $series);
            $source = $manual->versions[$series] ?? null;
            $dir = null !== $folder ? $folder.'/'.DocsFetcher::SOURCES : null;
            $php = null !== $dir && is_dir($dir) && !str_starts_with($manual->package, '@') ? new PhpSource($dir) : null;
            $this->apis[$id] = null !== $php && null !== $source && [] !== $php->classes()
                ? new Version($series, $php, $source['branch'], $this->commit($manual, $series) ?? $source['ref'], $source['eol'])
                : null;
        }

        return $this->apis[$id];
    }
}
