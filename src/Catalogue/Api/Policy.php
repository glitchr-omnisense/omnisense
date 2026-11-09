<?php

namespace App\Catalogue\Api;

/**
 * A provider's usage policy, as its own pages say it on the date it was read:
 * what it takes to call it, whether there is a free tier, whether that tier
 * allows commercial use, the credit it asks for, the page that says so.
 *
 * The keys of fromArray() are those of Omnisense\Contracts\Policy's export, so
 * that the export can replace config/apis.yaml's `policy:` block provider by
 * provider without touching the pages.
 */
final readonly class Policy
{
    public const AUTH = ['none', 'key', 'oauth', 'contract'];
    public const COMMERCIAL = ['yes', 'no', 'conditional', 'unclear'];
    public const ATTRIBUTION = ['required', 'per-item', 'encouraged', 'none', 'unclear'];

    /**
     * @param array<string, string> $note locale => one sentence
     */
    public function __construct(
        public string $auth,
        public bool $free,
        public string $commercial,
        public string $attribution,
        public ?string $credit,
        public array $note,
        public ?string $terms,
        public ?string $read,
    ) {
    }

    /** @param array<string, mixed> $data */
    public static function fromArray(array $data): self
    {
        $pick = static function (string $key, array $allowed) use ($data): string {
            $value = (string) ($data[$key] ?? 'unclear');
            if (!\in_array($value, $allowed, true)) {
                throw new \InvalidArgumentException(\sprintf('A policy\'s "%s" is one of %s, "%s" given.', $key, implode(', ', $allowed), $value));
            }

            return $value;
        };

        return new self(
            auth: $pick('auth', self::AUTH),
            free: (bool) ($data['free'] ?? false),
            commercial: $pick('commercial', self::COMMERCIAL),
            attribution: $pick('attribution', self::ATTRIBUTION),
            credit: isset($data['credit']) ? (string) $data['credit'] : null,
            note: array_map('strval', (array) ($data['note'] ?? [])),
            terms: isset($data['terms']) ? (string) $data['terms'] : null,
            read: isset($data['read']) ? (string) $data['read'] : null,
        );
    }

    public function note(string $locale): ?string
    {
        return $this->note[$locale] ?? $this->note['en'] ?? null;
    }
}
