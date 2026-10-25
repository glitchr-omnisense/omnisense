<?php

namespace App\Content;

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

    /** @var array<string, list<DocPage>> */
    private array $trees = [];

    /** @var array<string, Rendered> */
    private array $rendered = [];

    /** @var array<string, mixed>|null */
    private ?array $lock = null;

    public function __construct(
        private readonly Markdown $markdown,
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
                $this->manuals[(string) $key] = new Manual((string) $key, (string) $entry['package'], (string) $entry['repository'], (string) ($entry['default'] ?? array_key_first($versions)), $versions);
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
     * A series' pages, as a tree: the folders are sections, the files pages,
     * ordered by their front matter's order or a number before their name
     * ("10-getting-started.md"), then by name. The home page (index.md or
     * README.md at the root) is not in the tree.
     *
     * @return list<DocPage>
     */
    public function tree(Manual $manual, string $series): array
    {
        $id = $manual->key.'@'.$series;
        if (!isset($this->trees[$id])) {
            $folder = $this->folder($manual, $series);
            $this->trees[$id] = null !== $folder ? $this->scan($folder, '') : [];
        }

        return $this->trees[$id];
    }

    /** @return list<DocPage> every page of the series, depth first, the home first if it has one */
    public function pages(Manual $manual, string $series): array
    {
        $flat = [];
        $home = $this->home($manual, $series);
        if (null !== $home) {
            $flat[] = $home;
        }
        $walk = static function (array $nodes) use (&$walk, &$flat): void {
            foreach ($nodes as $node) {
                if (!$node->isSection()) {
                    $flat[] = $node;
                }
                $walk($node->children);
            }
        };
        $walk($this->tree($manual, $series));

        return $flat;
    }

    public function home(Manual $manual, string $series): ?DocPage
    {
        $folder = $this->folder($manual, $series);
        foreach (['index.md', 'README.md', 'readme.md'] as $name) {
            if (null !== $folder && is_file($folder.'/'.$name)) {
                return new DocPage('', $this->titleOf($folder.'/'.$name, $manual->package), $name, 0);
            }
        }

        return null;
    }

    public function page(Manual $manual, string $series, string $path): ?DocPage
    {
        foreach ($this->pages($manual, $series) as $page) {
            if ($page->path === $path) {
                return $page;
            }
        }

        return null;
    }

    /**
     * A page rendered: its links to other pages of the series go to their
     * addresses ($pageUrl), its links to other files of the repository and
     * its images to GitHub at the commit read - never to a moving branch.
     *
     * @param callable(string $path, ?string $fragment): string $pageUrl
     */
    public function render(Manual $manual, string $series, DocPage $page, callable $pageUrl): Rendered
    {
        $id = $manual->key.'@'.$series.':'.$page->path;
        if (isset($this->rendered[$id])) {
            return $this->rendered[$id];
        }
        $folder = $this->folder($manual, $series) ?? throw new \LogicException(sprintf('%s %s was not fetched.', $manual->key, $series));
        $source = $manual->versions[$series];
        $commit = $this->commit($manual, $series) ?? $source['ref'];
        $byFile = [];
        foreach ($this->pages($manual, $series) as $other) {
            $byFile[$other->file] = $other->path;
        }
        $base = \dirname((string) $page->file);
        $base = '.' === $base ? '' : $base;

        $links = function (string $url, bool $image) use ($byFile, $base, $source, $manual, $commit, $pageUrl): ?string {
            if ('' === $url || str_starts_with($url, '#') || preg_match('#^[a-z][a-z0-9+.-]*:|^//#i', $url)) {
                return null;
            }
            [$target, $fragment] = explode('#', $url, 2) + [1 => null];
            $target = self::normalise(('' !== $base ? $base.'/' : '').urldecode($target));
            if (!$image && isset($byFile[$target])) {
                return $pageUrl($byFile[$target], $fragment);
            }
            if (!$image && isset($byFile[$target.'.md'])) {
                return $pageUrl($byFile[$target.'.md'], $fragment);
            }
            // Outside the pages: the file in the repository, at the commit read.
            $inRepository = self::normalise(('' !== $source['path'] ? $source['path'].'/' : '').$target);
            if (str_starts_with($inRepository, '../')) {
                return null;
            }

            return $image
                ? 'https://raw.githubusercontent.com/'.$manual->repository.'/'.$commit.'/'.$inRepository
                : 'https://github.com/'.$manual->repository.'/blob/'.$commit.'/'.$inRepository.(null !== $fragment ? '#'.$fragment : '');
        };

        return $this->rendered[$id] = $this->markdown->render((string) file_get_contents($folder.'/'.$page->file), $links);
    }

    /** "guides/../install.md" → "install.md". */
    private static function normalise(string $path): string
    {
        $parts = [];
        foreach (explode('/', $path) as $part) {
            if ('' === $part || '.' === $part) {
                continue;
            }
            if ('..' === $part && [] !== $parts && '..' !== end($parts)) {
                array_pop($parts);
                continue;
            }
            $parts[] = $part;
        }

        return implode('/', $parts);
    }

    /** @return list<DocPage> */
    private function scan(string $folder, string $relative): array
    {
        $nodes = [];
        $entries = scandir($folder.('' !== $relative ? '/'.$relative : '')) ?: [];
        foreach ($entries as $entry) {
            if ('.' === $entry[0]) {
                continue;
            }
            $file = ('' !== $relative ? $relative.'/' : '').$entry;
            $full = $folder.'/'.$file;
            [$order, $slug] = self::orderOf(pathinfo($entry, \PATHINFO_FILENAME));
            $path = ('' !== $relative ? self::slugPath($relative).'/' : '').$slug;
            if (is_dir($full)) {
                $children = $this->scan($folder, $file);
                if ([] !== $children) {
                    $nodes[] = new DocPage($path, self::humanise($slug), null, $order, $children);
                }
            } elseif ('md' === strtolower(pathinfo($entry, \PATHINFO_EXTENSION)) && !('' === $relative && \in_array(strtolower($entry), ['index.md', 'readme.md'], true))) {
                $front = $this->frontOrder($full);
                $nodes[] = new DocPage($path, $this->titleOf($full, self::humanise($slug)), $file, $front ?? $order);
            }
        }
        usort($nodes, static fn (DocPage $a, DocPage $b): int => [$a->order, $a->path] <=> [$b->order, $b->path]);

        return $nodes;
    }

    /** "10-getting-started" → [10, "getting-started"]. */
    private static function orderOf(string $name): array
    {
        return 1 === preg_match('/^(\d+)[-_ .](.+)$/', $name, $m) ? [(int) $m[1], self::slug($m[2])] : [100, self::slug($name)];
    }

    private static function slugPath(string $relative): string
    {
        return implode('/', array_map(static fn (string $part): string => self::orderOf($part)[1], explode('/', $relative)));
    }

    private static function slug(string $name): string
    {
        return trim((string) preg_replace('/[^a-z0-9]+/', '-', strtolower($name)), '-') ?: 'page';
    }

    private static function humanise(string $slug): string
    {
        return ucfirst(str_replace('-', ' ', $slug));
    }

    private function titleOf(string $file, string $fallback): string
    {
        $head = (string) file_get_contents($file, false, null, 0, 8192);
        if (preg_match('/\A---\R(.*?)\R---/s', $head, $front) && preg_match('/^title:\s*["\']?(.+?)["\']?\s*$/m', $front[1], $m)) {
            return $m[1];
        }
        if (preg_match('/^#\s+(.+?)\s*#*\s*$/m', (string) preg_replace('/^```.*?^```/ms', '', $head), $m)) {
            return trim(strip_tags((string) preg_replace(['/`([^`]*)`/', '/\[([^\]]*)\]\([^)]*\)/', '/[*_]{1,2}([^*_]+)[*_]{1,2}/'], '$1', $m[1])));
        }

        return $fallback;
    }

    private function frontOrder(string $file): ?int
    {
        $head = (string) file_get_contents($file, false, null, 0, 2048);

        return preg_match('/\A---\R(.*?)\R---/s', $head, $front) && preg_match('/^(?:order|weight|sidebar_position):\s*(\d+)/m', $front[1], $m) ? (int) $m[1] : null;
    }
}
