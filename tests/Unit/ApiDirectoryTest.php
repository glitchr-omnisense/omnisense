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
            self::assertNotNull($catalogue->family($family->name), $family->name.' is available: catalog/'.$family->name.'.yaml lists it');
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
        foreach (['omnireview', 'omnifund', 'omniproof', 'omnitally'] as $gone) {
            self::assertNull($directory->family($gone), $gone.' has no card of its own');
        }
        $reach = $directory->family('omnireach');
        self::assertSame(['omnireview', 'omnifund'], $reach->merged);
        $byPackage = [];
        foreach ($reach->providers as $provider) {
            $byPackage[$provider->package] = $provider;
        }
        // From the real catalogue: catalog/omnireview.yaml and catalog/omnifund.yaml until the merge is imported.
        $review = array_values(array_filter($reach->providers, static fn ($p): bool => \in_array('reviews', $p->capabilities, true)));
        $sponsors = array_values(array_filter($reach->providers, static fn ($p): bool => \in_array('sponsors', $p->capabilities, true)));
        self::assertNotEmpty($review, 'the review platforms, under omnireach');
        self::assertNotEmpty($sponsors, 'the funding platforms, under omnireach');
        self::assertSame(['audience', 'search-queries'], $byPackage['omnireach/matomo']->capabilities, 'its own providers keep their capabilities');
        self::assertSame(['signature'], $directory->family('omnisign')->providers[0]->capabilities, 'a released signature provider does not check identities');
        self::assertSame(Status::Reserved, $directory->family('omnilang')->status);
        self::assertSame(Status::Reserved, $directory->family('omnical')->status);
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
