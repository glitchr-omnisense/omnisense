<?php

namespace App\Site;

use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Who the site is - the Twig global `site`: the header, the footer, the
 * legal notice. Omniscient is the showcase of the Omni packages and the
 * place their manuals are read; it is published by the studio that writes
 * them.
 */
class Identity
{
    public function __construct(
        #[Autowire('%app.site.name%')] private readonly string $name,
        #[Autowire('%app.site.publisher%')] private readonly string $publisher,
        #[Autowire('%app.site.host%')] private readonly string $host,
        #[Autowire('%app.site.github%')] private readonly string $github,
        #[Autowire('%env(MAILER_CONTACT)%')] private readonly string $contact,
        #[Autowire('%app.site.url%')] private readonly string $url = 'http://localhost:8135',
    ) {
    }

    /** The static site's public origin, without a trailing slash (SITE_URL). */
    public function getUrl(): string
    {
        return rtrim($this->url, '/');
    }

    /** An address of the site made absolute at its public origin: "/news" → "https://…/news". */
    public function absolute(string $path): string
    {
        return $this->getUrl().'/'.ltrim($path, '/');
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getPublisher(): string
    {
        return $this->publisher;
    }

    /** Who hosts the site, for the legal notice; empty until it is known. */
    public function getHost(): ?string
    {
        return $this->host ?: null;
    }

    /** The organisation the packages are published under, on GitHub. */
    public function getGithub(): string
    {
        return $this->github;
    }

    public function getGithubUrl(): string
    {
        return 'https://github.com/'.$this->github;
    }

    public function getEmail(): string
    {
        return $this->contact;
    }

    public function getMaker(): string
    {
        return 'Glitch Art';
    }

    public function getMakerUrl(): string
    {
        return 'https://glitchr.io';
    }
}
