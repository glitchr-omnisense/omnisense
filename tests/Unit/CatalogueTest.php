<?php

namespace App\Tests\Unit;

use App\Catalogue\CatalogueImporter;
use App\Catalogue\Families;
use App\Catalogue\Package;
use App\Catalogue\RepositoryScanner;
use App\Catalogue\Tier;
use App\Catalogue\Verification;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Filesystem\Filesystem;
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
        // The omniscient/ vendor holds the core and the families' own packages, as glitchr/ did and still does until each moves.
        self::assertSame([Tier::Family, 'omnilab'], Tier::of('omniscient/omnilab'));
        self::assertSame([Tier::Family, 'omnilab'], Tier::of('glitchr/omnilab'));
        self::assertSame([Tier::Foundation, 'omnibase'], Tier::of('omniscient/omnibase'));
        self::assertSame([Tier::Bridge, null], Tier::of('omniscient/omnibridge'));
        self::assertSame([Tier::Provider, 'omnilab'], Tier::of('omnilab/pubchem'));
        self::assertNull(Tier::of('omniscient/website'));
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

        // A workspace ("project") and another vendor's package are not the catalogue's.
        self::assertSame(['@glitchr/flowjs', 'glitchr/omnibase', 'glitchr/omnibus', 'glitchr/omnitrade', 'omnibase/blog', 'omnibus/offline', 'omnitrade/paypal', 'omnitrade/stripe'], array_keys($packages));

        $trade = $packages['glitchr/omnitrade'];
        self::assertSame([Tier::Family, 'omnitrade', 'omnitrade/core', 'LGPL-3.0-or-later', 2], [$trade->tier, $trade->family, $trade->path, $trade->license, $trade->docPages]);
        self::assertSame('glitchr-studio/omnitrade', $trade->repository, 'from composer.json\'s support.source when the folder is no git working copy');
        self::assertSame(['payment', 'gateway'], $trade->keywords);
        self::assertContains('glitchr/omnitrade', $packages['omnitrade/stripe']->requires);
        self::assertSame(0, $packages['omnibus/offline']->docPages);
        self::assertNull($packages['omnibus/offline']->readme);
        self::assertSame('glitchr-studio/flowjs', $packages['@glitchr/flowjs']->repository);
        self::assertSame('glitchr-studio/omnilab', RepositoryScanner::repositoryOf('git@github.com:glitchr-studio/omnilab.git'));
        self::assertNull(RepositoryScanner::repositoryOf('git@gitlab.example.org:web/site.git'), 'off GitHub: not public, not listed');
    }

    public function testTheImporterWritesOneFilePerFamilyAndKeepsWhatAMaintainerWrote(): void
    {
        $fs = new Filesystem();
        $dir = sys_get_temp_dir().'/omniscient-import-'.bin2hex(random_bytes(4));
        $fs->mkdir($dir.'/catalog');
        file_put_contents($dir.'/catalog/omnitrade.yaml', "family: omnitrade\nphrase:\n  en: Written by hand.\npackages: {}\n");
        try {
            $report = (new CatalogueImporter(new RepositoryScanner(\dirname(__DIR__).'/Fixtures/repos'), $dir.'/catalog', $dir.'/documentation.yaml', $dir.'/translations'))->import();

            self::assertSame(['libraries.yaml', 'omnibase.yaml', 'omnibus.yaml', 'omnitrade.yaml'], array_values(array_diff(scandir($dir.'/catalog'), ['.', '..'])));
            $trade = Yaml::parseFile($dir.'/catalog/omnitrade.yaml');
            self::assertSame(['en' => 'Written by hand.'], $trade['phrase'], 'the phrase written by hand is kept');
            // The providers whose repository is not public on GitHub are left out, and said so.
            self::assertSame(['glitchr/omnitrade'], array_keys($trade['packages']));
            self::assertSame(['omnibus/offline', 'omnitrade/paypal', 'omnitrade/stripe'], $report['skipped']);
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

    public function testAPackageIsAddressedAndRequiredByItsBranch(): void
    {
        $lab = Package::fromArray('omniscient/omnilab', ['branch' => '1.x'], 'omnilab');
        self::assertSame([Tier::Family, 'omnilab', 'omnilab', '1.x-dev'], [$lab->getTier(), $lab->getFamily(), $lab->getKey(), $lab->getConstraint()]);
        self::assertSame('composer require omniscient/omnilab:1.x-dev', $lab->getInstallCommand());

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
    }
}
