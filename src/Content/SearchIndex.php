<?php

namespace App\Content;

use App\Catalogue\Catalogue;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

/**
 * The search index of one language, written with the site as a JSON file
 * (/search.json, /fr/search.json) and read by the reader's browser
 * (assets/search.js): the guides, the news, every package of the catalogue
 * and every page of each manual's default series - a title, an address, a
 * kind, a short text and the page's headings. Sorted, so that two builds
 * write the same file.
 */
class SearchIndex
{
    public function __construct(
        private readonly Guides $guides,
        private readonly News $news,
        private readonly Catalogue $catalogue,
        private readonly Documentation $documentation,
        private readonly UrlGeneratorInterface $urls,
    ) {
    }

    /** @return array{locale: string, entries: list<array<string, string>>} */
    public function build(string $locale): array
    {
        $entries = [];
        foreach ($this->guides->all($locale) as $guide) {
            $entries[] = self::entry('guide', $guide->title, $this->urls->generate('app_guide', ['slug' => $guide->slug, '_locale' => $locale]), $guide->description, $guide->rendered->headings);
        }
        foreach ($this->news->all($locale) as $item) {
            $entries[] = self::entry('news', $item->title, $this->urls->generate('app_news_item', ['slug' => $item->slug, '_locale' => $locale]), $item->summary, [], $item->date->format('Y-m-d'));
        }
        foreach ($this->catalogue->all() as $package) {
            $entries[] = self::entry($package->getTier()->kind(), $package->getName(), $this->urls->generate('app_package', ['vendor' => $package->getVendor(), 'name' => $package->getShortName(), '_locale' => $locale]), (string) $package->getDescription(), [], implode(' ', $package->getKeywords()));
        }
        foreach ($this->documentation->manuals() as $manual) {
            $series = $manual->default;
            if (null === $this->documentation->folder($manual, $series)) {
                continue;
            }
            $url = fn (string $to, ?string $fragment): string => $this->urls->generate('' === $to ? 'app_manual' : 'app_manual_page', array_filter(['key' => $manual->key, 'series' => $series, 'path' => $to, '_locale' => $locale], static fn ($v): bool => '' !== $v)).(null !== $fragment ? '#'.$fragment : '');
            foreach ($this->documentation->pages($manual, $series) as $page) {
                $rendered = $this->documentation->render($manual, $series, $page, $url);
                $entries[] = self::entry('doc', '' === $page->path ? $manual->package : $page->title, $url($page->path, null), $rendered->excerpt(160), $rendered->headings, $manual->package.' '.$series);
            }
        }

        return ['locale' => $locale, 'entries' => $entries];
    }

    /**
     * @param list<array{level: int, id: string, text: string}> $headings
     *
     * @return array<string, string>
     */
    private static function entry(string $kind, string $title, string $url, string $text, array $headings = [], string $extra = ''): array
    {
        return array_filter([
            'k' => $kind,
            't' => $title,
            'u' => $url,
            'd' => $text,
            'h' => implode(' · ', array_map(static fn (array $h): string => $h['text'], array_filter($headings, static fn (array $h): bool => $h['level'] <= 3))),
            'x' => $extra,
        ], static fn (string $v): bool => '' !== $v);
    }
}
