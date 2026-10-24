<?php

namespace App\Content;

/**
 * A Markdown text once rendered: its HTML (without its first-level title,
 * which the page prints itself), that title, the headings for "on this
 * page", its front matter, and a plain-text excerpt for the search index.
 */
final class Rendered
{
    /**
     * @param list<array{level: int, id: string, text: string}> $headings
     * @param array<string, mixed>                              $meta
     */
    public function __construct(
        public readonly string $html,
        public readonly ?string $title,
        public readonly array $headings,
        public readonly array $meta,
        public readonly string $text,
    ) {
    }

    public function excerpt(int $length = 220): string
    {
        $text = trim((string) preg_replace('/\s+/', ' ', $this->text));

        return mb_strlen($text) > $length ? rtrim(mb_substr($text, 0, $length - 1)).'…' : $text;
    }
}
