<?php

namespace App\Tests\Functional;

use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * What the functional tests share: a browser on http://localhost, reading the
 * tests' own content (tests/Fixtures: a catalogue, a fetched manual, news) and
 * the site's guides. No database, no network: the pages are what the static
 * export renders.
 */
abstract class SiteTestCase extends WebTestCase
{
    protected KernelBrowser $client;

    protected function setUp(): void
    {
        $this->client = static::createClient([], ['HTTP_HOST' => 'localhost']);
        $this->client->disableReboot();
    }

    protected function get(string $path, int $status = 200): string
    {
        $this->client->request('GET', $path);
        self::assertSame($status, $this->client->getResponse()->getStatusCode(), $path);

        return (string) $this->client->getResponse()->getContent();
    }
}
