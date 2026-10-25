<?php

namespace App\Content;

use App\Catalogue\Catalogue;
use Omnidocs\Model\Heading;
use Omnidocs\OnePage;
use Omnidocs\Search\Entry;
use Omnidocs\Search\SearchIndex as OmnidocsIndex;
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
            // A package with a manual leads to it, never to a page that leads to it.
            $manual = $this->documentation->manualOf($package->getName());
            $url = null !== $manual
                ? $this->urls->generate('app_manual', ['key' => $manual->key, 'series' => $manual->default, '_locale' => $locale])
                : $this->urls->generate('app_package', ['vendor' => $package->getVendor(), 'name' => $package->getShortName(), '_locale' => $locale]);
            $entries[] = self::entry($package->getTier()->kind(), $package->getName(), $url, (string) $package->getDescription(), [], implode(' ', $package->getKeywords()));
        }
        foreach ($this->documentation->manuals() as $manual) {
            $series = $manual->default;
            $url = $this->urls->generate('app_manual', ['key' => $manual->key, 'series' => $series, '_locale' => $locale]);
            $one = $this->documentation->onePage($manual, $series, $url);
            if (null !== $one) {
                // The one page, each of its parts (a page of docs/), each heading: page#anchor.
                $index = (new OmnidocsIndex())->onePage($one, $manual->package, 'doc', $manual->package.' '.$series, symbols: false);
                array_push($entries, ...array_map(static fn (Entry $e): array => $e->toArray(), $index->entries()));
            }
            $api = $this->documentation->api($manual, $series);
            if (null !== $api) {
                $apiUrl = $this->urls->generate('app_manual_api', ['key' => $manual->key, 'series' => $series, '_locale' => $locale]);
                $apiPage = new OnePage($api, $this->documentation->linker($manual, $series, $apiUrl), $apiUrl);
                foreach ($api->symbols() as $symbol) {
                    if (\in_array($symbol->kind, ['class', 'interface', 'trait', 'enum', 'option'], true)) {
                        $entries[] = (new Entry('symbol', $symbol->name, $apiPage->urlOf($symbol->page, $symbol->anchor), $symbol->summary, extra: $symbol->kind.' '.$manual->package.' '.$series, page: $manual->package, signature: ''))->toArray();
                    }
                }
            }
        }

        return ['locale' => $locale, 'entries' => $entries];
    }

    /**
     * @param list<Heading> $headings
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
            'h' => implode(' · ', array_map(static fn (Heading $h): string => $h->text, array_filter($headings, static fn (Heading $h): bool => $h->level <= 3))),
            'x' => $extra,
        ], static fn (string $v): bool => '' !== $v);
    }
}
