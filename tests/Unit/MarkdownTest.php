<?php

namespace App\Tests\Unit;

use App\Content\Markdown;
use PHPUnit\Framework\TestCase;

/** Markdown as every page renders it: the title out of the prose, ids on headings, links rewritten, raw HTML escaped. */
final class MarkdownTest extends TestCase
{
    public function testTheFirstHeadingIsTheTitleAndTheOthersHaveIds(): void
    {
        $rendered = (new Markdown())->render("---\ntitle: From the front matter\norder: 3\n---\n\n# The `title`\n\nText.\n\n## A section\n\n### A sub-section\n");
        self::assertSame('The title', $rendered->title);
        self::assertSame(['title' => 'From the front matter', 'order' => 3], $rendered->meta);
        self::assertStringNotContainsString('<h1', $rendered->html);
        self::assertSame([['level' => 2, 'id' => 'a-section', 'text' => 'A section'], ['level' => 3, 'id' => 'a-sub-section', 'text' => 'A sub-section']], $rendered->headings);
        self::assertStringContainsString('<h2 id="a-section">', $rendered->html);
    }

    public function testLinksAndImagesAreRewrittenAndRawHtmlIsEscaped(): void
    {
        $rendered = (new Markdown())->render("[a page](other.md) [out](https://example.org) ![img](pic.png)\n\n<iframe src=\"x\"></iframe>\n\n> [!TIP]\n> A shortcut.\n", static fn (string $url, bool $image): ?string => match (true) {
            $image => 'https://cdn.example/'.$url,
            str_ends_with($url, '.md') => '/docs/x/1.x/other',
            default => null,
        });
        self::assertStringContainsString('<a href="/docs/x/1.x/other">a page</a>', $rendered->html);
        self::assertStringContainsString('<a href="https://example.org">out</a>', $rendered->html);
        self::assertStringContainsString('<img src="https://cdn.example/pic.png" alt="img" />', $rendered->html);
        self::assertStringContainsString('&lt;iframe', $rendered->html);
        self::assertStringContainsString('<blockquote class="doc-callout doc-callout-tip" data-label="Tip"><p>', $rendered->html);
        self::assertSame('a page out img A shortcut.', trim((string) preg_replace('/\s+/', ' ', $rendered->excerpt())));
    }
}
