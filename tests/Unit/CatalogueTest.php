<?php

namespace App\Tests\Unit;

use App\Catalogue\CatalogueImporter;
use App\Catalogue\Families;
use App\Catalogue\Package;
use App\Catalogue\PackagistReader;
use App\Catalogue\RepositoryScanner;
use App\Catalogue\Tier;
use App\Catalogue\Verification;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;
use Symfony\Component\Yaml\Yaml;

/** The catalogue's rules, without a kernel: a name's floor, a README's word, what a repository says, the files the importer writes. */
final class CatalogueTest extends TestCase
{
    public function testANamePutsAPackageOnItsFloor(): void
    {
        self::assertSame([Tier::Foundation, 'omnibase'], Tier::of('glitchr/omnibase'));
        self::assertSame([Tier::Component, 'omnibase'], Tier::of('omnibase/marketplace'));
        self::assertSame([Tier::Family, 'omnitrade'], Tier::of('glitchr/omnitrade'));
        self::assertSame([Tier::Provider, 'omnitrade'], Tier::of('omnitrade/stripe'));
        self::assertSame([Tier::Bridge, null], Tier::of('omnibridge/symfony'));
        self::assertSame([Tier::Bridge, null], Tier::of('glitchr/omnibridge'));
        self::assertSame([Tier::Library, null], Tier::of('@glitchr/flowjs'));
        // The omnisense/ vendor holds the core and the families' own packages, as glitchr/ did and still does until each moves.
        self::assertSame([Tier::Family, 'omnilab'], Tier::of('omnisense/omnilab'));
        self::assertSame([Tier::Family, 'omnilab'], Tier::of('glitchr/omnilab'));
        self::assertSame([Tier::Foundation, 'omnibase'], Tier::of('omnisense/omnibase'));
        self::assertSame([Tier::Bridge, null], Tier::of('omnisense/omnibridge'));
        // The contracts every family builds on: a core of its own, beside omnibase's.
        self::assertSame([Tier::Foundation, 'contracts'], Tier::of('omnisense/contracts'));
        self::assertSame([Tier::Provider, 'omnilab'], Tier::of('omnilab/pubchem'));
        self::assertNull(Tier::of('omnisense/website'));
        // What the search calls each floor.
        self::assertSame(['core', 'structure', 'family', 'provider', 'bridge', 'library'], array_map(static fn (Tier $t): string => $t->kind(), Tier::cases()));
        self::assertSame(Tier::Component, Tier::fromKind('structure'));
        self::assertNull(Tier::fromKind('component'));
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

    public function testTheRepositoriesAreReadWhereTheyAre(): void
    {
        $packages = (new RepositoryScanner(\dirname(__DIR__).'/Fixtures/repos'))->scan();

        // A workspace ("project"), another vendor's package and a template's example (omnimap/example) are not the catalogue's.
        self::assertSame(['@glitchr/flowjs', 'glitchr/omnibase', 'glitchr/omnibus', 'glitchr/omnitrade', 'omnibase/blog', 'omnibus/offline', 'omnisense/omnifilm', 'omnisense/omnilang', 'omnitrade/paypal', 'omnitrade/stripe'], array_keys($packages));

        $trade = $packages['glitchr/omnitrade'];
        self::assertSame([Tier::Family, 'omnitrade', 'omnitrade/core', 'LGPL-3.0-or-later', 2], [$trade->tier, $trade->family, $trade->path, $trade->license, $trade->docPages]);
        self::assertSame('glitchr-omnisense/omnitrade', $trade->repository, 'from composer.json\'s support.source when the folder is no git working copy');
        self::assertSame(['payment', 'gateway'], $trade->keywords);
        self::assertContains('glitchr/omnitrade', $packages['omnitrade/stripe']->requires);
        self::assertSame(0, $packages['omnibus/offline']->docPages);
        self::assertNull($packages['omnibus/offline']->readme);
        self::assertSame('glitchr-studio/flowjs', $packages['@glitchr/flowjs']->repository);
        self::assertSame('glitchr-omnisense/omnilab', RepositoryScanner::repositoryOf('git@github.com:glitchr-omnisense/omnilab.git'));
        self::assertNull(RepositoryScanner::repositoryOf('git@gitlab.example.org:web/site.git'), 'off GitHub: not public, not listed');
    }

    public function testTheImporterWritesOneFilePerFamilyAndKeepsWhatAMaintainerWrote(): void
    {
        $fs = new Filesystem();
        $dir = sys_get_temp_dir().'/omnisense-import-'.bin2hex(random_bytes(4));
        $fs->mkdir($dir.'/catalog');
        file_put_contents($dir.'/catalog/omnitrade.yaml', "family: omnitrade\nphrase:\n  en: Written by hand.\npackages: {}\n");
        // A family merged into another since the last import (Families::MERGED): its file goes, its packages are not listed.
        file_put_contents($dir.'/catalog/omnifilm.yaml', "family: omnifilm\npackages:\n  omnisense/omnifilm: {kind: family}\n");
        try {
            $report = (new CatalogueImporter(new RepositoryScanner(\dirname(__DIR__).'/Fixtures/repos'), $dir.'/catalog', $dir.'/documentation.yaml', $dir.'/translations'))->import();

            self::assertSame(['libraries.yaml', 'omnibase.yaml', 'omnibus.yaml', 'omnitrade.yaml'], array_values(array_diff(scandir($dir.'/catalog'), ['.', '..'])));
            $trade = Yaml::parseFile($dir.'/catalog/omnitrade.yaml');
            self::assertSame(['en' => 'Written by hand.'], $trade['phrase'], 'the phrase written by hand is kept');
            // The providers whose repository is not public on GitHub are left out, and said so.
            self::assertSame(['glitchr/omnitrade'], array_keys($trade['packages']));
            self::assertSame(['omnibus/offline', 'omnitrade/paypal', 'omnitrade/stripe'], $report['skipped']);
            self::assertSame(['omnisense/omnifilm'], $report['merged'], 'a merged family\'s working copy, still on disk, is not listed');
            // A name only reserved (keyword "reserved"): no file, no entry - config/apis.yaml shows it as reserved.
            self::assertSame(['omnisense/omnilang'], $report['reserved']);
            self::assertSame('family', $trade['packages']['glitchr/omnitrade']['kind']);
            self::assertSame(['glitchr/omnibus'], array_keys(Yaml::parseFile($dir.'/catalog/omnibus.yaml')['packages']));
            self::assertSame(['glitchr/omnibase'], Yaml::parseFile($dir.'/catalog/omnibase.yaml')['packages']['omnibase/blog']['requires']);
            // Without a published head (no git here), no documentation series is pinned.
            self::assertArrayNotHasKey('docs', $trade['packages']['glitchr/omnitrade']);
            self::assertSame(['packages' => []], Yaml::parseFile($dir.'/documentation.yaml'));
            self::assertSame(5, $report['packages']);
        } finally {
            $fs->remove($dir);
        }
    }

    public function testTheImporterReadsEachReleaseFromPackagist(): void
    {
        $fs = new Filesystem();
        $dir = sys_get_temp_dir().'/omnisense-import-'.bin2hex(random_bytes(4));
        $fs->mkdir($dir.'/catalog');
        // What the last import wrote: kept when Packagist does not answer.
        file_put_contents($dir.'/catalog/omnibase.yaml', "family: omnibase\npackages:\n  glitchr/omnibase: {kind: core, version: 4.0.0}\n");
        // A manual written by hand for a package without a docs/ folder (its README, path ''): kept as it is.
        $byHand = ['package' => 'glitchr/omnitrade', 'repository' => 'glitchr-omnisense/omnitrade', 'default' => '1.x', 'versions' => ['1.x' => ['branch' => '1.x', 'ref' => str_repeat('a', 40), 'pinned' => 'branch-head', 'path' => '']]];
        file_put_contents($dir.'/documentation.yaml', Yaml::dump(['packages' => ['omnitrade' => $byHand]], 6, 2));
        $asked = [];
        $http = new MockHttpClient(static function (string $method, string $url) use (&$asked): MockResponse {
            $asked[] = $url;

            return match ($url) {
                // Minified as Packagist serves it: the newest first, each version only what changed.
                'https://repo.packagist.org/p2/glitchr/omnitrade.json' => new MockResponse(json_encode(['minified' => 'composer/2.0', 'packages' => ['glitchr/omnitrade' => [
                    ['name' => 'glitchr/omnitrade', 'version' => 'v1.3.0-RC1', 'version_normalized' => '1.3.0.0-RC1', 'abandoned' => 'omnisense/omnitrade'],
                    ['version' => 'v1.2.0', 'version_normalized' => '1.2.0.0'],
                    ['version' => 'v1.10.0', 'version_normalized' => '1.10.0.0', 'abandoned' => '__unset'],
                ]]])),
                'https://repo.packagist.org/p2/glitchr/omnibus.json' => new MockResponse('', ['http_code' => 404]),
                'https://repo.packagist.org/p2/omnibase/blog.json' => new MockResponse(json_encode(['packages' => ['omnibase/blog' => []]])),
                default => new MockResponse('', ['http_code' => 503]),
            };
        });
        try {
            $report = (new CatalogueImporter(new RepositoryScanner(\dirname(__DIR__).'/Fixtures/repos'), $dir.'/catalog', $dir.'/documentation.yaml', $dir.'/translations', new PackagistReader($http)))->import();

            // Composer packages only: the npm library is not asked.
            self::assertCount(4, $asked);
            self::assertNotContains('https://repo.packagist.org/p2/@glitchr/flowjs.json', $asked);
            $trade = Yaml::parseFile($dir.'/catalog/omnitrade.yaml')['packages']['glitchr/omnitrade'];
            // The highest stable release, not the first listed nor a release candidate; abandoned as the newest says.
            self::assertSame(['1.10.0', 'omnisense/omnitrade'], [$trade['version'], $trade['abandoned']]);
            self::assertArrayNotHasKey('version', Yaml::parseFile($dir.'/catalog/omnibus.yaml')['packages']['glitchr/omnibus'], 'not on Packagist: no version');
            $base = Yaml::parseFile($dir.'/catalog/omnibase.yaml')['packages'];
            self::assertArrayNotHasKey('version', $base['omnibase/blog'], 'nothing tagged: no version');
            self::assertSame('4.0.0', $base['glitchr/omnibase']['version'], 'no answer: the version written before is kept');
            self::assertSame(['glitchr/omnibase'], $report['unanswered']);
            self::assertSame(2, $report['released']);
            self::assertSame(['omnitrade' => $byHand], Yaml::parseFile($dir.'/documentation.yaml')['packages']);
            self::assertTrue($trade['docs'], 'its manual written by hand: documented');
        } finally {
            $fs->remove($dir);
        }
    }

    public function testAReleasedPackageInstallsWithoutAConstraint(): void
    {
        $trade = Package::fromArray('omnisense/omnitrade', ['branch' => '1.x', 'version' => '1.3.0'], 'omnitrade');
        self::assertSame(['1.3.0', '^1.3', true], [$trade->getVersion(), $trade->getConstraint(), $trade->isStable()]);
        self::assertSame('composer require omnisense/omnitrade', $trade->getInstallCommand());
        self::assertSame('^2.0', Package::fromArray('omnibus/colissimo', ['branch' => '2.x', 'version' => '2.0.0'], 'omnibus')->getConstraint());

        $oauth = Package::fromArray('omniauth/oauth', ['branch' => '1.x', 'abandoned' => true], 'omniauth');
        self::assertSame([true, null, false], [$oauth->isAbandoned(), $oauth->getReplacement(), $oauth->isStable()]);
        self::assertSame('omnisense/omniauth', Package::fromArray('omniauth/oauth', ['abandoned' => 'omnisense/omniauth'], 'omniauth')->getReplacement());
        self::assertFalse($trade->isAbandoned());
    }

    public function testAPackageIsAddressedAndRequiredByItsBranch(): void
    {
        $lab = Package::fromArray('omnisense/omnilab', ['branch' => '1.x'], 'omnilab');
        self::assertSame([Tier::Family, 'omnilab', 'omnilab', '1.x-dev'], [$lab->getTier(), $lab->getFamily(), $lab->getKey(), $lab->getConstraint()]);
        self::assertSame('composer require omnisense/omnilab:1.x-dev', $lab->getInstallCommand());

        $colissimo = Package::fromArray('omnibus/colissimo', ['branch' => '2.0'], 'omnibus');
        self::assertSame(['omnibus-colissimo', '2.0.x-dev', Tier::Provider], [$colissimo->getKey(), $colissimo->getConstraint(), $colissimo->getTier()]);
        self::assertSame('dev-main', Package::fromArray('omnibase/blog', ['branch' => 'main'], 'omnibase')->getConstraint());

        $flow = Package::fromArray('@glitchr/flowjs', [], 'libraries');
        self::assertSame(['glitchr', 'flowjs', Tier::Library, null, 'npm install @glitchr/flowjs'], [$flow->getVendor(), $flow->getShortName(), $flow->getTier(), $flow->getFamily(), $flow->getInstallCommand()]);

        $this->expectException(\InvalidArgumentException::class);
        Package::fromArray('acme/thing', [], 'other');
    }

    public function testFamilies(): void
    {
        self::assertSame('trade', Families::suffix('omnitrade'));
        self::assertSame(['omnibase', 'omnitrade', 'omnimeet', 'omnizzz'], array_keys(Families::ordered(['omnizzz' => 1, 'omnimeet' => 1, 'omnitrade' => 1, 'omnibase' => 1])));
        // The families merged on 2026-11-09 and 2026-11-10, each into the family that took its packages; none of them still ordered.
        self::assertSame(['omnifilm' => 'omnishow', 'omnigame' => 'omniplay', 'omnishare' => 'omnipost', 'omnireach' => 'omnipulse', 'omnireview' => 'omnipulse', 'omnifund' => 'omnipulse'], Families::MERGED);
        self::assertSame([], array_intersect(array_keys(Families::MERGED), Families::ORDER));
        self::assertSame([], array_diff(Families::MERGED, Families::ORDER), 'each successor has its place');
    }
}
