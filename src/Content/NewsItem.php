<?php

namespace App\Content;

use Omnidocs\Model\Rendered;

/** One piece of news: news/<YYYY-MM-DD>-<slug>.<locale>.md. */
final class NewsItem
{
    /**
     * @param list<string> $tags
     */
    public function __construct(
        public readonly string $slug,
        public readonly string $locale,
        public readonly \DateTimeImmutable $date,
        public readonly string $title,
        public readonly string $summary,
        public readonly array $tags,
        public readonly ?string $author,
        public readonly Rendered $rendered,
        public readonly string $source,
    ) {
    }
}
