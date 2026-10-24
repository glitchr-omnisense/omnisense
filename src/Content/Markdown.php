<?php

namespace App\Content;

use League\CommonMark\Environment\Environment;
use League\CommonMark\Event\DocumentParsedEvent;
use League\CommonMark\Extension\CommonMark\CommonMarkCoreExtension;
use League\CommonMark\Extension\CommonMark\Node\Block\Heading;
use League\CommonMark\Extension\CommonMark\Node\Inline\Image;
use League\CommonMark\Extension\CommonMark\Node\Inline\Link;
use League\CommonMark\Extension\FrontMatter\FrontMatterExtension;
use League\CommonMark\Extension\FrontMatter\Output\RenderedContentWithFrontMatter;
use League\CommonMark\Extension\GithubFlavoredMarkdownExtension;
use League\CommonMark\Extension\HeadingPermalink\HeadingPermalink;
use League\CommonMark\Extension\HeadingPermalink\HeadingPermalinkExtension;
use League\CommonMark\MarkdownConverter;
use League\CommonMark\Node\Inline\Text;
use League\CommonMark\Node\Node;
use League\CommonMark\Node\StringContainerHelper;

/**
 * Markdown as every page of the site renders it - the guides, the news,
 * each package's documentation: CommonMark with GitHub's extensions (tables,
 * task lists, autolinks, strikethrough), a front matter, an id on every
 * heading, GitHub's alerts as callouts. Raw HTML is escaped: the packages' documentation comes from other
 * repositories and is not trusted to write the site's markup.
 *
 * $links rewrites the address of each link and image (a relative ".md" link
 * between two pages of a manual, an image beside them): it receives the
 * address as written and answers the one to print, or null to keep it.
 */
final class Markdown
{
    /**
     * @param (callable(string $url, bool $image): ?string)|null $links
     */
    public function render(string $markdown, ?callable $links = null): Rendered
    {
        $environment = new Environment([
            'html_input' => 'escape',
            'allow_unsafe_links' => false,
            'heading_permalink' => ['symbol' => '', 'insert' => 'after', 'id_prefix' => '', 'fragment_prefix' => '', 'apply_id_to_heading' => true, 'html_class' => 'heading-anchor', 'aria_hidden' => true],
        ]);
        $environment->addExtension(new CommonMarkCoreExtension());
        $environment->addExtension(new GithubFlavoredMarkdownExtension());
        $environment->addExtension(new FrontMatterExtension());
        $environment->addExtension(new HeadingPermalinkExtension());

        $title = null;
        $headings = [];
        $text = '';
        $environment->addEventListener(DocumentParsedEvent::class, static function (DocumentParsedEvent $event) use ($links, &$title, &$text): void {
            $document = $event->getDocument();
            foreach ($document->iterator() as $node) {
                if (null !== $links && ($node instanceof Link || $node instanceof Image)) {
                    $url = $links($node->getUrl(), $node instanceof Image);
                    if (null !== $url) {
                        $node->setUrl($url);
                    }
                }
            }
            // The first first-level heading is the page's title: printed by the page, not in the prose.
            foreach ($document->children() as $child) {
                if ($child instanceof Heading && 1 === $child->getLevel()) {
                    $title = trim(StringContainerHelper::getChildText($child, [HeadingPermalink::class]));
                    $child->detach();
                    break;
                }
            }
            $text = self::plain($document);
        }, 10);

        // After the permalinks are given (their processor listens at -100).
        $environment->addEventListener(DocumentParsedEvent::class, static function (DocumentParsedEvent $event) use (&$headings): void {
            foreach ($event->getDocument()->iterator() as $node) {
                if ($node instanceof Heading) {
                    $id = '';
                    foreach ($node->children() as $child) {
                        if ($child instanceof HeadingPermalink) {
                            $id = $child->getSlug();
                        }
                    }
                    $headings[] = ['level' => $node->getLevel(), 'id' => $id, 'text' => trim(StringContainerHelper::getChildText($node, [HeadingPermalink::class]))];
                }
            }
        }, -200);

        $result = (new MarkdownConverter($environment))->convert($markdown);
        $meta = $result instanceof RenderedContentWithFrontMatter ? (array) $result->getFrontMatter() : [];

        return new Rendered(self::callouts($result->getContent()), $title, $headings, $meta, $text);
    }

    /** GitHub's alerts ("> [!NOTE]") as the manuals' callouts. */
    private static function callouts(string $html): string
    {
        return (string) preg_replace_callback('#<blockquote>\s*<p>\[!(NOTE|TIP|IMPORTANT|WARNING|CAUTION)\]\s*#', static fn (array $m): string => sprintf('<blockquote class="doc-callout doc-callout-%s" data-label="%s"><p>', strtolower($m[1]), ucfirst(strtolower($m[1]))), $html);
    }

    private static function plain(Node $document): string
    {
        $out = [];
        foreach ($document->iterator() as $node) {
            if ($node instanceof Text) {
                $out[] = $node->getLiteral();
            }
        }

        return (string) preg_replace('/\[!(?:NOTE|TIP|IMPORTANT|WARNING|CAUTION)\]\s*/', '', implode(' ', $out));
    }
}
