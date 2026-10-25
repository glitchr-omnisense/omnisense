<?php

namespace App\Content;

use Omnidocs\Markdown\MarkdownRenderer;
use Symfony\Component\DependencyInjection\Attribute\Autoconfigure;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * The news: Markdown files committed under news/, one per item and
 * language (news/2026-10-24-static-website.en.md). Authors open a pull
 * request; its preview build shows the item; the merge publishes it with
 * the feeds. The date is the file name's, the title and summary the front
 * matter's (or the first heading and paragraph).
 *
 * Public: static.php reads it from the container for the routes' parameters.
 */
#[Autoconfigure(public: true)]
class News
{
    /** @var array<string, array<string, NewsItem>> */
    private array $items = [];

    public function __construct(
        private readonly MarkdownRenderer $markdown,
        #[Autowire('%app.news_dir%')] private readonly string $dir,
    ) {
    }

    /** @return array<string, NewsItem> by slug, latest first */
    public function all(string $locale): array
    {
        if (!isset($this->items[$locale])) {
            $items = [];
            foreach (glob(rtrim($this->dir, '/').'/*.'.$locale.'.md') ?: [] as $file) {
                if (1 !== preg_match('/^(\d{4}-\d{2}-\d{2})-([a-z0-9-]+)\.[a-z]{2}\.md$/', basename($file), $match)) {
                    throw new \UnexpectedValueException(sprintf('news/%s: the name must be <YYYY-MM-DD>-<slug>.<locale>.md.', basename($file)));
                }
                $rendered = $this->markdown->render((string) file_get_contents($file));
                $items[$match[2]] = new NewsItem(
                    slug: $match[2],
                    locale: $locale,
                    date: new \DateTimeImmutable($match[1].' 00:00:00', new \DateTimeZone('UTC')),
                    title: (string) ($rendered->meta['title'] ?? $rendered->title ?? $match[2]),
                    summary: (string) ($rendered->meta['summary'] ?? $rendered->excerpt()),
                    tags: array_values(array_map('strval', (array) ($rendered->meta['tags'] ?? []))),
                    author: isset($rendered->meta['author']) ? (string) $rendered->meta['author'] : null,
                    rendered: $rendered,
                    source: 'news/'.basename($file),
                );
            }
            uasort($items, static fn (NewsItem $a, NewsItem $b): int => [$b->date, $a->slug] <=> [$a->date, $b->slug]);
            $this->items[$locale] = $items;
        }

        return $this->items[$locale];
    }

    public function get(string $locale, string $slug): ?NewsItem
    {
        return $this->all($locale)[$slug] ?? null;
    }

    /** @return list<string> every slug, in any language */
    public function slugs(string $locale): array
    {
        return array_keys($this->all($locale));
    }
}
