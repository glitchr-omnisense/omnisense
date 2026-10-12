<?php

namespace App\Tests\Unit;

use App\Catalogue\Families;
use App\Catalogue\PackageRegistries;
use App\Catalogue\RepositoryScanner;
use App\Catalogue\Tier;
use App\Catalogue\Verification;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

/** The catalogue's rules, without a kernel: a name's floor, a README's word, a registry's answer, what a repository says. */
final class CatalogueTest extends TestCase
{
    public function testANamePutsAPackageOnItsFloor(): void
    {
        self::assertSame([Tier::Foundation, 'omnibase'], Tier::of('glitchr/omnibase'));
        self::assertSame([Tier::Component, 'omnibase'], Tier::of('omnibase/marketplace'));
        self::assertSame([Tier::Family, 'omnitrade'], Tier::of('glitchr/omnitrade'));
        self::assertSame([Tier::Provider, 'omnitrade'], Tier::of('omnitrade/stripe'));
        self::assertSame([Tier::Library, null], Tier::of('@glitchr/flowjs'));
        // Not the ecosystem's: another vendor, another scope, a helper of the studio.
        self::assertNull(Tier::of('symfony/http-client'));
        self::assertNull(Tier::of('@hotwired/stimulus'));
        self::assertNull(Tier::of('glitchr/typesense-bundle'));
        self::assertNull(Tier::of('canva/workspace'));
    }

    public function testAReadmeSaysWhetherItWasTriedForReal(): void
    {
        [$state, $note] = Verification::read("# x\n\nVerified against the real service on 2026-10-04 (see [docs/](docs/index.md)).\n");
        self::assertSame(Verification::Verified, $state);
        self::assertSame('Verified against the real service on 2026-10-04 (see docs/).', $note);

        [$state, $note] = Verification::read("# x\n\n> **Unverified.** Written from the published documentation and recorded answers;\n> it has not yet been run against a real sandbox.\n");
        self::assertSame(Verification::Unverified, $state);
        self::assertSame('Unverified. Written from the published documentation and recorded answers; it has not yet been run against a real sandbox.', $note);

        // A reservation under a section of its own is about a part of the package.
        [$state] = Verification::read("# x\n\nShips parcels.\n\n## The fresh option\n\nNot verified against a real contract: the codes are the public ones.\n");
        self::assertSame(Verification::Partial, $state);
        // The section that IS the statement speaks of the whole package.
        [$state] = Verification::read("# x\n\nReads texts.\n\n## Not verified against the real service\n\nIt needs credentials.\n");
        self::assertSame(Verification::Unverified, $state);
        // Both said: in part.
        [$state] = Verification::read("# x\n\nVerified against the real service on 2026-10-04.\n\nThe refunds are unverified.\n");
        self::assertSame(Verification::Partial, $state);

        // A family's table lists its providers' states: they are not its own. Nor is a word that only looks like it.
        [$state, $note] = Verification::read("# x\n\n| Package | |\n|---|---|\n| `a/b` | Stripe (unverified) |\n\nPayPal verifies the signature itself.\n\n```sh\n# not verified against anything\n```\n");
        self::assertSame(Verification::Unstated, $state);
        self::assertNull($note);
    }

    public function testARegistryAnswersItIsThereItIsNotOrNothing(): void
    {
        $calls = [];
        $http = new MockHttpClient(function (string $method, string $url) use (&$calls): MockResponse {
            $calls[] = $url;

            return match (true) {
                str_ends_with($url, '/packages/glitchr/omnitrade.json') => new MockResponse(json_encode(['package' => ['versions' => ['dev-feature' => [], '1.x-dev' => [], '2.x-dev' => []], 'downloads' => ['total' => 13]]])),
                str_ends_with($url, '/packages/omnitrade/web.json') => new MockResponse('{"status":"error"}', ['http_code' => 404]),
                str_contains($url, 'registry.npmjs.org/@glitchr%2Fflowjs') => new MockResponse(json_encode(['dist-tags' => ['latest' => '1.0.0']])),
                str_contains($url, 'api.npmjs.org/downloads/point/last-month/@glitchr/flowjs') => new MockResponse(json_encode(['downloads' => 750])),
                default => new MockResponse('', ['http_code' => 503]),
            };
        });
        $registries = new PackageRegistries($http, new ArrayAdapter());

        $state = $registries->state('glitchr/omnitrade');
        self::assertSame([true, '2.x-dev', 13], [$state->published, $state->version, $state->downloads]);
        self::assertFalse($registries->state('omnitrade/web')->published);
        $state = $registries->state('@glitchr/flowjs');
        self::assertSame([true, '1.0.0', 750], [$state->published, $state->version, $state->downloads]);

        // Kept: asked once.
        $before = \count($calls);
        $registries->state('glitchr/omnitrade');
        self::assertCount($before, $calls);
        // ...unless asked afresh.
        $registries->state('glitchr/omnitrade', fresh: true);
        self::assertCount($before + 1, $calls);

        // A registry that does not answer: nothing is known, and nothing is kept of that silence.
        self::assertNull($registries->state('glitchr/omnibus'));
        self::assertNull($registries->state('glitchr/omnibus'));
        self::assertCount($before + 3, $calls);
    }

    public function testTheNewestVersion(): void
    {
        self::assertSame('1.4.0', PackageRegistries::newest(['1.x-dev', '1.4.0', 'v1.3.9', 'dev-main']));
        self::assertSame('3.x-dev', PackageRegistries::newest(['1.x-dev', '3.x-dev', '2.x-dev', 'dev-fix']));
        self::assertSame('dev-main', PackageRegistries::newest(['dev-main']));
        self::assertNull(PackageRegistries::newest([]));
    }

    public function testTheRepositoriesAreReadWhereTheyAre(): void
    {
        $scanner = new RepositoryScanner(\dirname(__DIR__).'/Fixtures/repos', \dirname(__DIR__).'/Fixtures/sites');
        $packages = $scanner->scan();

        // A workspace ("project") and another vendor's package are not the catalogue's.
        self::assertSame(['@glitchr/flowjs', 'glitchr/omnibase', 'glitchr/omnibus', 'glitchr/omnitrade', 'omnibase/blog', 'omnibus/offline', 'omnitrade/paypal', 'omnitrade/stripe'], array_keys($packages));

        $trade = $packages['glitchr/omnitrade'];
        self::assertSame([Tier::Family, 'omnitrade', 'omnitrade/core', 'LGPL-3.0-or-later', 2], [$trade->tier, $trade->family, $trade->path, $trade->license, $trade->docPages]);
        self::assertSame('https://github.com/glitchr-studio/omnitrade', $trade->repositoryUrl);
        self::assertSame(['payment', 'gateway'], $trade->keywords);
        self::assertContains('glitchr/omnitrade', $packages['omnitrade/stripe']->requires);
        self::assertSame(0, $packages['omnibus/offline']->docPages);
        self::assertNull($packages['omnibus/offline']->readme);
        self::assertSame('https://github.com/glitchr-studio/flowjs', $packages['@glitchr/flowjs']->repositoryUrl);

        $sites = $scanner->sites();
        self::assertSame(['juriste', 'nakaya', 'taratata'], array_keys($sites));
        self::assertContains('omnitrade/stripe', $sites['nakaya']);
        self::assertContains('@glitchr/flowjs', $sites['juriste'], 'a site\'s package.json counts too');
    }

    public function testFamilies(): void
    {
        self::assertSame('trade', Families::suffix('omnitrade'));
        self::assertSame(['omnibase', 'omnitrade', 'omnimeet', 'omnizzz'], array_keys(Families::ordered(['omnizzz' => 1, 'omnimeet' => 1, 'omnitrade' => 1, 'omnibase' => 1])));
        // An announced name stops being one the day its family is found.
        self::assertArrayHasKey('omnishow', Families::announced(['omnibase', 'omnitrade']));
        self::assertArrayNotHasKey('omnishow', Families::announced(['omnishow']));
        self::assertSame([], array_intersect(array_keys(Families::ANNOUNCED), Families::ORDER), 'a family is announced or it exists');
    }
}
