<?php

namespace App\Catalogue;

use Symfony\Contracts\HttpClient\Exception\ExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * What Packagist serves of a package: its latest stable release and whether
 * it is marked abandoned. Read from Packagist's public metadata (the p2 file
 * of the tagged releases, repo.packagist.org/p2/<name>.json - the file
 * Composer itself reads), anonymously, a few requests at a time; no token,
 * no API call.
 *
 * Used by `app:catalog:import`, a maintainer's tool: the website never asks
 * Packagist anything at request time, it reads catalog/*.yaml.
 */
class PackagistReader
{
    private const URL = 'https://repo.packagist.org/p2/%s.json';

    /** Requests in flight at once: Packagist's metadata is a CDN, still asked politely. */
    private const CONCURRENCY = 6;

    public function __construct(private readonly HttpClientInterface $http)
    {
    }

    /**
     * By name: the latest stable version (null: none tagged, or not on Packagist) and abandoned (true, or the
     * replacement's name); null when Packagist did not answer.
     *
     * @param list<string> $names the Composer names
     *
     * @return array<string, array{version: ?string, abandoned: bool|string}|null>
     */
    public function read(array $names): array
    {
        $out = [];
        foreach (array_chunk($names, self::CONCURRENCY) as $chunk) {
            $responses = [];
            foreach ($chunk as $name) {
                $responses[$name] = $this->http->request('GET', \sprintf(self::URL, $name), [
                    'headers' => ['User-Agent' => 'omnisense-website catalogue import (+https://github.com/glitchr-omnisense/omnisense)'],
                    'timeout' => 20,
                ]);
            }
            foreach ($responses as $name => $response) {
                try {
                    $status = $response->getStatusCode();
                    $out[$name] = match (true) {
                        404 === $status => ['version' => null, 'abandoned' => false],
                        200 === $status => self::latest($response->toArray()['packages'][$name] ?? []),
                        default => null,
                    };
                } catch (ExceptionInterface) {
                    $out[$name] = null;
                }
            }
        }

        return $out;
    }

    /**
     * The latest stable release among the versions of a p2 file. The file is
     * "minified": each version lists only what changed from the one before it,
     * "__unset" removing a key.
     *
     * @param list<array<string, mixed>> $versions
     *
     * @return array{version: ?string, abandoned: bool|string}
     */
    public static function latest(array $versions): array
    {
        $latest = null;
        $abandoned = false;
        $current = [];
        foreach ($versions as $i => $diff) {
            foreach ((array) $diff as $key => $value) {
                if ('__unset' === $value) {
                    unset($current[$key]);
                } else {
                    $current[$key] = $value;
                }
            }
            // The file lists the newest release first: its abandoned flag is the package's.
            if (0 === $i) {
                $abandoned = \is_string($current['abandoned'] ?? null) ? $current['abandoned'] : (bool) ($current['abandoned'] ?? false);
            }
            $normalized = (string) ($current['version_normalized'] ?? '');
            // Stable only: 1.0.0.0, never 1.0.0.0-RC1 nor a branch.
            if (1 !== preg_match('/^\d+\.\d+\.\d+\.\d+$/', $normalized)) {
                continue;
            }
            if (null === $latest || version_compare($normalized, $latest[0], '>')) {
                $latest = [$normalized, ltrim((string) $current['version'], 'vV')];
            }
        }

        return ['version' => $latest[1] ?? null, 'abandoned' => $abandoned];
    }
}
