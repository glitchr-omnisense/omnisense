<?php

namespace App\Site;

use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Yaml\Yaml;

/**
 * The site's repository and its stars on GitHub - the Twig global `stars`,
 * the "Star" button of the header. The count is read at build time by
 * app:stars:fetch into config/github.yaml; the pages never ask GitHub
 * (the privacy page promises that they load nothing from other services).
 * Without the file, or without a count in it, the button is shown without
 * a number.
 */
class Stars
{
    private ?array $data = null;

    public function __construct(
        #[Autowire('%app.site.repository%')] private readonly string $repository,
        #[Autowire('%app.github_file%')] private readonly string $file,
    ) {
    }

    /** "glitchr-omnisense/omnisense" */
    public function getRepository(): string
    {
        return $this->repository;
    }

    public function getUrl(): string
    {
        return 'https://github.com/'.$this->repository;
    }

    /** The count read at the last build, or null when none was read. */
    public function getCount(): ?int
    {
        $count = $this->read()['stars'] ?? null;

        return \is_int($count) && $count >= 0 ? $count : null;
    }

    /** "1.2k" above a thousand, as GitHub writes it. */
    public function getShort(): ?string
    {
        $count = $this->getCount();
        if (null === $count) {
            return null;
        }

        return $count < 1000 ? (string) $count : rtrim(rtrim(number_format($count / 1000, 1, '.', ''), '0'), '.').'k';
    }

    /** @return array<string, mixed> */
    private function read(): array
    {
        if (null === $this->data) {
            try {
                $this->data = is_file($this->file) ? (array) Yaml::parseFile($this->file) : [];
            } catch (\Throwable) {
                $this->data = [];
            }
        }

        return $this->data;
    }
}
