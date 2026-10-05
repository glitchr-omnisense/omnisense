<?php

namespace App\Tests\Mock;

use Base\Wikidoc\Search\TypesenseClientInterface;

/**
 * Typesense as the tests see it (services.yaml, when@test: it stands for
 * omnibase/docs' TypesenseClientInterface): collections kept in memory for
 * the whole run, a search that answers in the shape the real server answers
 * (hits, document, highlights, text_match, found) - or a server that is
 * down ($up = false), as when the "search" container is stopped.
 */
final class FakeTypesense implements TypesenseClientInterface
{
    public static bool $up = true;

    /** @var array<string, list<array<string, mixed>>> the documents, by collection */
    public static array $collections = [];

    /** @var list<list<string>> the collections asked, search by search */
    public static array $searches = [];

    public static function reset(): void
    {
        self::$up = true;
        self::$collections = [];
        self::$searches = [];
    }

    public function isAvailable(): bool
    {
        return self::$up;
    }

    public function collections(): array
    {
        $this->guard();

        return array_keys(self::$collections);
    }

    public function createCollection(array $schema): void
    {
        $this->guard();
        self::$collections[$schema['name']] = [];
    }

    public function dropCollection(string $name): void
    {
        $this->guard();
        unset(self::$collections[$name]);
    }

    public function import(string $collection, array $documents): int
    {
        $this->guard();
        self::$collections[$collection] = array_merge(self::$collections[$collection] ?? [], $documents);

        return \count($documents);
    }

    public function multiSearch(array $searches): array
    {
        $this->guard();
        self::$searches[] = array_column($searches, 'collection');

        $results = [];
        foreach ($searches as $search) {
            if (!isset(self::$collections[$search['collection']])) {
                $results[] = ['code' => 404, 'error' => 'Not found.'];
                continue;
            }
            // A typo is forgiven, as the real engine forgives it: "webhok" finds "webhook".
            $query = strtr(mb_strtolower((string) $search['q']), ['webhok' => 'webhook']);
            $hits = [];
            foreach (self::$collections[$search['collection']] as $document) {
                $title = mb_strtolower(($document['title'] ?? '').' '.($document['section'] ?? ''));
                $text = (string) ($document['text'] ?? '');
                if (!str_contains($title.' '.mb_strtolower($text), $query)) {
                    continue;
                }
                $hits[] = [
                    'document' => array_diff_key($document, ['text' => true]),
                    'highlights' => [['field' => 'text', 'snippet' => '<mark>'.mb_substr($text, 0, 60).'</mark>']],
                    // As the server scores: a 64-bit integer, the matched field's weight in its low bits.
                    'text_match' => 578730123365187584 + (str_contains($title, $query) ? 4 : 1),
                ];
            }
            $results[] = ['found' => \count($hits), 'hits' => \array_slice($hits, 0, (int) ($search['per_page'] ?? 10))];
        }

        return $results;
    }

    private function guard(): void
    {
        if (!self::$up) {
            throw new \RuntimeException('Typesense is down.');
        }
    }
}
