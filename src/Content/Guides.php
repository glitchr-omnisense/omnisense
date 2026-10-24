<?php

namespace App\Content;

use Symfony\Component\DependencyInjection\Attribute\Autoconfigure;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

/**
 * The website's own guides, the pages that hold for every package: one
 * folder per language under docs/ (docs/en/getting-started.md,
 * docs/fr/getting-started.md - the same slug in each language, so that a
 * page and its translation are each other's alternates). The front matter
 * gives the title, a description and the order.
 *
 * Public: static.php reads it from the container for the routes' parameters.
 */
#[Autoconfigure(public: true)]
class Guides
{
    /** @var array<string, array<string, Guide>> */
    private array $guides = [];

    public function __construct(
        private readonly Markdown $markdown,
        private readonly UrlGeneratorInterface $urls,
        #[Autowire('%app.guides_dir%')] private readonly string $dir,
    ) {
    }

    /** @return array<string, Guide> by slug, in their order */
    public function all(string $locale): array
    {
        if (!isset($this->guides[$locale])) {
            $guides = [];
            $files = glob(rtrim($this->dir, '/').'/'.$locale.'/*.md') ?: [];
            $slugs = array_map(static fn (string $f): string => basename($f, '.md'), $files);
            // A link to another guide ("compatibility.md#bridges") goes to its page in the same language.
            $links = fn (string $url): ?string => preg_match('/^([a-z0-9-]+)\.md(#.*)?$/', $url, $m) && \in_array($m[1], $slugs, true)
                ? $this->urls->generate('app_guide', ['slug' => $m[1], '_locale' => $locale]).($m[2] ?? '')
                : null;
            foreach ($files as $file) {
                $slug = basename($file, '.md');
                $rendered = $this->markdown->render((string) file_get_contents($file), $links);
                $guides[$slug] = new Guide(
                    slug: $slug,
                    locale: $locale,
                    title: (string) ($rendered->meta['title'] ?? $rendered->title ?? $slug),
                    description: (string) ($rendered->meta['description'] ?? $rendered->excerpt(160)),
                    order: (int) ($rendered->meta['order'] ?? 100),
                    rendered: $rendered,
                    source: 'docs/'.$locale.'/'.$slug.'.md',
                );
            }
            uasort($guides, static fn (Guide $a, Guide $b): int => [$a->order, $a->slug] <=> [$b->order, $b->slug]);
            $this->guides[$locale] = $guides;
        }

        return $this->guides[$locale];
    }

    public function get(string $locale, string $slug): ?Guide
    {
        return $this->all($locale)[$slug] ?? null;
    }

    /**
     * Every slug written in at least one language: a guide not yet translated
     * is shown in the language it exists in.
     *
     * @return list<string>
     */
    public function slugs(): array
    {
        $slugs = [];
        foreach (glob(rtrim($this->dir, '/').'/*/*.md') ?: [] as $file) {
            $slugs[basename($file, '.md')] = true;
        }
        ksort($slugs);

        return array_keys($slugs);
    }
}
