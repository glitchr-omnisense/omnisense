<?php

namespace App\Twig;

use App\Catalogue\Api\Directory;
use App\Catalogue\Api\Family;
use App\Catalogue\Api\Provider;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

/**
 * What the cards of /apis ask: a capability's label, the haystack a card is
 * searched in (lower case, without accents, as apis_controller.js folds the
 * question) and the GitHub issue a proposal opens.
 */
class ApiExtension extends AbstractExtension
{
    public function __construct(private readonly Directory $directory)
    {
    }

    public function getFunctions(): array
    {
        return [
            new TwigFunction('api_label', $this->directory->label(...)),
            new TwigFunction('api_haystack', self::haystack(...)),
            new TwigFunction('api_issue', self::issue(...)),
        ];
    }

    /** The words a card is found by: its names, its phrase or description, its capabilities. */
    public static function haystack(Family|Provider $card, string $locale): string
    {
        $parts = $card instanceof Family
            ? [$card->name, $card->phrase($locale), ...$card->capabilities]
            : [$card->package, $card->name, $card->family, $card->getDescription() ?? '', $card->policy?->note($locale) ?? '', ...$card->capabilities];

        return self::fold(implode(' ', $parts));
    }

    /** A new issue of the site's repository, from one of its templates, its title filled in. */
    public static function issue(string $repository, string $template, string $title = ''): string
    {
        return 'https://github.com/'.$repository.'/issues/new?'.http_build_query(array_filter(['template' => $template, 'title' => $title]), '', '&', \PHP_QUERY_RFC3986);
    }

    public static function fold(string $text): string
    {
        $ascii = class_exists(\Transliterator::class) ? \Transliterator::create('NFD; [:Nonspacing Mark:] Remove; NFC')?->transliterate($text) : null;

        return mb_strtolower($ascii ?? $text);
    }
}
