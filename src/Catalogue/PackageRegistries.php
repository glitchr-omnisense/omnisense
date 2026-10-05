<?php

namespace App\Catalogue;

use Psr\Log\LoggerInterface;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Is a package on its registry? Packagist for a Composer package, npm for a
 * JavaScript one - their public APIs, no key:
 *
 *   https://packagist.org/packages/<vendor>/<name>.json    200 with versions and downloads, 404 when absent
 *   https://registry.npmjs.org/<name>                       200 with dist-tags, 404 when absent
 *   https://api.npmjs.org/downloads/point/last-month/<name>
 *
 * An answer is kept half a day (cache.app). When the registry cannot be
 * reached - no network, a timeout, a 5xx - state() returns null: nothing is
 * known, and the caller keeps what the last answer said.
 */
class PackageRegistries
{
    private const TTL = 43200;

    public function __construct(
        private readonly HttpClientInterface $http,
        private readonly CacheInterface $cache,
        private readonly ?LoggerInterface $logger = null,
    ) {
    }

    public function state(string $name, bool $fresh = false): ?RegistryState
    {
        $key = 'app.registry.'.md5($name);
        if ($fresh) {
            $this->cache->delete($key);
        }

        try {
            return $this->cache->get($key, function (ItemInterface $item) use ($name): RegistryState {
                $item->expiresAfter(self::TTL);

                return str_starts_with($name, '@') || !str_contains($name, '/') ? $this->npm($name) : $this->packagist($name);
            });
        } catch (\Throwable $e) {
            // Not cached: the next run asks again.
            $this->logger?->notice('The registry did not answer about {package}: {message}', ['package' => $name, 'message' => $e->getMessage()]);

            return null;
        }
    }

    private function packagist(string $name): RegistryState
    {
        $response = $this->http->request('GET', 'https://packagist.org/packages/'.$name.'.json', ['timeout' => 8]);
        $status = $response->getStatusCode();
        if (404 === $status) {
            return new RegistryState(false);
        }
        if (200 !== $status) {
            throw new \RuntimeException('Packagist answered '.$status);
        }
        $package = $response->toArray()['package'] ?? [];
        $versions = array_keys((array) ($package['versions'] ?? []));

        return new RegistryState(true, self::newest($versions), isset($package['downloads']['total']) ? (int) $package['downloads']['total'] : null);
    }

    private function npm(string $name): RegistryState
    {
        $response = $this->http->request('GET', 'https://registry.npmjs.org/'.str_replace('/', '%2F', $name), ['timeout' => 8, 'headers' => ['Accept' => 'application/vnd.npm.install-v1+json']]);
        $status = $response->getStatusCode();
        if (404 === $status) {
            return new RegistryState(false);
        }
        if (200 !== $status) {
            throw new \RuntimeException('npm answered '.$status);
        }
        $version = $response->toArray()['dist-tags']['latest'] ?? null;

        $downloads = null;
        try {
            $count = $this->http->request('GET', 'https://api.npmjs.org/downloads/point/last-month/'.$name, ['timeout' => 8]);
            $downloads = 200 === $count->getStatusCode() ? (int) ($count->toArray()['downloads'] ?? 0) : null;
        } catch (\Throwable) {
            // The count is a detail: the package is there.
        }

        return new RegistryState(true, \is_string($version) ? $version : null, $downloads);
    }

    /**
     * The newest of Packagist's versions: a tagged release if there is one,
     * else the highest development branch ("2.x-dev" before "1.x-dev").
     *
     * @param list<string> $versions
     */
    public static function newest(array $versions): ?string
    {
        $versions = array_values(array_filter(array_map('strval', $versions), static fn (string $v): bool => !str_starts_with($v, 'dev-') || \in_array($v, ['dev-main', 'dev-master'], true)));
        if ([] === $versions) {
            return null;
        }
        $tagged = array_values(array_filter($versions, static fn (string $v): bool => 1 === preg_match('/^v?\d+(\.\d+)*(-(alpha|beta|rc)\.?\d*)?$/i', $v)));
        $pool = [] !== $tagged ? $tagged : $versions;
        usort($pool, static fn (string $a, string $b): int => version_compare(self::comparable($b), self::comparable($a)));

        return $pool[0];
    }

    private static function comparable(string $version): string
    {
        $version = ltrim(strtolower($version), 'v');
        if (str_starts_with($version, 'dev-')) {
            return '0';
        }

        return str_replace(['.x-dev', '-dev', 'x'], ['.9999', '', '9999'], $version);
    }
}
