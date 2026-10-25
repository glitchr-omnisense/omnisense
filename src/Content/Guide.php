<?php

namespace App\Content;

use Omnidocs\Model\Rendered;

/** A cross-ecosystem guide: docs/<locale>/<slug>.md. */
final class Guide
{
    public function __construct(
        public readonly string $slug,
        public readonly string $locale,
        public readonly string $title,
        public readonly string $description,
        public readonly int $order,
        public readonly Rendered $rendered,
        public readonly string $source,
    ) {
    }
}
