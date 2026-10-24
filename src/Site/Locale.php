<?php

namespace App\Site;

use Symfony\Component\HttpFoundation\Request;

/**
 * The site's language of a request: omnibase's Localizer gives the request a
 * full locale (en_GB, fr_FR), the content is written per language (en, fr).
 */
final class Locale
{
    public static function of(Request $request): string
    {
        return strtolower(substr($request->getLocale(), 0, 2));
    }
}
