<?php

namespace App\Tests\Unit;

use App\Catalogue\Api\Directory;
use App\Catalogue\Api\Policy;
use App\Catalogue\Api\Status;
use App\Catalogue\Catalogue;
use App\Site\Stars;
use PHPUnit\Framework\TestCase;

/**
 * config/apis.yaml as the site reads it, against the real catalogue: a
 * typo in it fails here, not as a filter that silently matches nothing.
 */
final class ApiDirectoryTest extends TestCase
{
    private static function directory(): Directory
    {
        $root = \dirname(__DIR__, 2);

        return new Directory(new Catalogue($root.'/catalog'), $root.'/config/apis.yaml');
    }

    public function testTheFileReadsAndEveryAvailableFamilyIsInTheCatalogue(): void
    {
        $directory = self::directory();
        $catalogue = new Catalogue(\dirname(__DIR__, 2).'/catalog');
        self::assertNotEmpty($directory->byStatus(Status::Available));

        foreach ($directory->byStatus(Status::Available) as $family) {
            // Available: in the catalogue (omnipulse since its merge was imported, the families it took in no longer).
            self::assertNotNull($catalogue->family($family->name), $family->name.' is available: catalog/ lists it');
            foreach ($family->merged as $merged) {
                self::assertNull($catalogue->family($merged), $merged.' went into '.$family->name.': no catalog file of its own');
            }
        }
        foreach ($directory->byStatus(Status::Wanted) as $family) {
            self::assertNull($catalogue->family($family->name)?->getOwn(), $family->name.' is wanted: its core is not in the catalogue');
        }
        foreach ([...$directory->byStatus(Status::Wanted), ...$directory->byStatus(Status::Reserved)] as $family) {
            self::assertNotEmpty($family->providers, $family->name.' names its first providers');
        }
    }

    public function testEveryDeclaredPolicySaysWhereAndWhenItWasRead(): void
    {
        $policies = 0;
        foreach (self::directory()->providers() as $provider) {
            if (null === $provider->policy) {
                continue;
            }
            ++$policies;
            self::assertStringStartsWith('https://', (string) $provider->policy->terms, $provider->package);
            self::assertMatchesRegularExpression('/^\d{4}-\d{2}-\d{2}$/', (string) $provider->policy->read, $provider->package);
            self::assertSame($provider->family, explode('/', $provider->package)[0], $provider->package.' is named after its family');
        }
        self::assertGreaterThan(30, $policies);
    }

    public function testTheReleasedProvidersAreCardsWithoutAPolicyUntilOneIsDeclared(): void
    {
        $omnibus = self::directory()->family('omnibus');
        self::assertNotNull($omnibus);
        self::assertGreaterThan(20, \count($omnibus->providers), 'the carriers, from catalog/omnibus.yaml');
        self::assertSame(Status::Available, $omnibus->providers[0]->status);
        self::assertSame('unknown', $omnibus->providers[0]->getAuth());
    }

    public function testTheMergedFamiliesProvidersAreListedUnderTheFamilyTheyWentInto(): void
    {
        $directory = self::directory();
        foreach (['omnireach', 'omnireview', 'omnifund', 'omniproof', 'omnitally'] as $gone) {
            self::assertNull($directory->family($gone), $gone.' has no card of its own');
        }
        $pulse = $directory->family('omnipulse');
        self::assertSame(['omnireach', 'omnireview', 'omnifund'], $pulse->merged, 'the families it took in, named on its card');
        $byPackage = [];
        foreach ($pulse->providers as $provider) {
            $byPackage[$provider->package] = $provider;
        }
        // The merge imported: catalog/omnipulse.yaml lists its eleven providers under their new names, each with what it answers.
        $names = array_keys($byPackage);
        sort($names);
        self::assertSame(['omnipulse/bing-webmaster', 'omnipulse/ga4', 'omnipulse/github-sponsors', 'omnipulse/google-reviews', 'omnipulse/liberapay', 'omnipulse/matomo', 'omnipulse/opencollective', 'omnipulse/plausible', 'omnipulse/search-console', 'omnipulse/tripadvisor', 'omnipulse/trustpilot'], $names);
        foreach ($byPackage as $package => $provider) {
            self::assertSame(Status::Available, $provider->status, $package);
            self::assertNotNull($provider->released, $package.' is in the catalogue');
            self::assertNull($provider->successor, $package.' is published under its own name');
        }
        self::assertSame(['audience'], $byPackage['omnipulse/matomo']->capabilities, 'each answers what it answers');
        self::assertSame(['search-queries'], $byPackage['omnipulse/search-console']->capabilities);
        self::assertSame(['reviews'], $byPackage['omnipulse/google-reviews']->capabilities);
        self::assertSame(['sponsors'], $byPackage['omnipulse/liberapay']->capabilities);
        self::assertSame(['signature'], $directory->family('omnisign')->providers[0]->capabilities, 'a released signature provider does not check identities');
        self::assertSame(Status::Reserved, $directory->family('omnilang')->status);
        self::assertSame(Status::Reserved, $directory->family('omnical')->status);
    }

    public function testADeclaredStatusMatchesTheCatalogue(): void
    {
        $directory = self::directory();
        foreach ($directory->providers() as $provider) {
            // A name reserved on Packagist and GitHub, nothing released: app:catalog:import leaves it out.
            if (Status::Available !== $provider->status) {
                self::assertNull($provider->released, $provider->package.' is '.$provider->status->value.': not in the catalogue');
            } else {
                self::assertNotNull($provider->released, $provider->package.' is available: in the catalogue');
            }
        }
        $statuses = static fn (string $family): array => array_combine(array_map(static fn ($p): string => $p->package, $directory->family($family)->providers), array_map(static fn ($p): string => $p->status->value, $directory->family($family)->providers));
        $sign = $statuses('omnisign');
        self::assertSame(['available', 'available', 'reserved', 'reserved'], [$sign['omnisign/stripe-identity'], $sign['omnisign/dossierfacile'], $sign['omnisign/veriff'], $sign['omnisign/entrust']]);
        $bill = $statuses('omnibill');
        self::assertSame(['available', 'available', 'reserved', 'reserved'], [$bill['omnibill/odoo'], $bill['omnibill/pennylane'], $bill['omnibill/xero'], $bill['omnibill/quickbooks']]);
        self::assertSame(Status::Available, $directory->family('omnitravel')->status);
        self::assertSame(Status::Available, $directory->family('omnistay')->status);
        self::assertSame(Status::Reserved, $directory->family('omnicrawl')->status);
    }

    public function testAPolicyRefusesAValueItDoesNotKnow(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        Policy::fromArray(['auth' => 'password']);
    }

    public function testTheStarCountIsReadOrLeftOut(): void
    {
        $file = tempnam(sys_get_temp_dir(), 'stars');
        file_put_contents($file, "repository: glitchr-omnisense/omnisense\nstars: 1234\n");
        $stars = new Stars('glitchr-omnisense/omnisense', $file);
        self::assertSame(1234, $stars->getCount());
        self::assertSame('1.2k', $stars->getShort());
        self::assertSame('https://github.com/glitchr-omnisense/omnisense', $stars->getUrl());
        unlink($file);

        self::assertNull((new Stars('glitchr-omnisense/omnisense', $file))->getCount(), 'no file: no count');
        self::assertNull((new Stars('glitchr-omnisense/omnisense', $file))->getShort());
    }
}
