<?php

namespace App\Tests\Unit;

use App\Catalogue\Api\DeclaredPolicies;
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

        return new Directory(new Catalogue($root.'/catalog'), new DeclaredPolicies($root.'/config/policies.json'), $root.'/config/apis.yaml');
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

    public function testEveryPolicySaysWhoDeclaredItAndWhereAndWhenItWasRead(): void
    {
        $declared = $typed = 0;
        foreach (self::directory()->providers() as $provider) {
            $policy = $provider->policy;
            if (null === $policy) {
                continue;
            }
            self::assertSame($provider->family, explode('/', $provider->package)[0], $provider->package.' is named after its family');
            if ($policy->declared) {
                // Its package's policy.json, at the published head it was read at.
                ++$declared;
                self::assertNotNull($provider->released, $provider->package.' declares its policy: it is released');
                self::assertMatchesRegularExpression('/^[0-9a-f]{40}$/', (string) $policy->ref, $provider->package);
                self::assertContains($policy->declaredBy, ['maintainer', 'provider', 'site'], $provider->package);
            } else {
                // Typed by the site for a provider with no package yet.
                ++$typed;
                self::assertNull($provider->released, $provider->package.': a released provider declares its own');
                self::assertSame('site', $policy->declaredBy);
                self::assertStringStartsWith('https://', (string) $policy->terms, $provider->package);
                self::assertMatchesRegularExpression('/^\d{4}-\d{2}-\d{2}$/', (string) $policy->verifiedAt, $provider->package);
            }
        }
        self::assertGreaterThanOrEqual(80, $declared, 'the families moved onto omnisense/contracts on 2026-11-10');
        self::assertGreaterThanOrEqual(15, $typed, 'the providers wanted or reserved');
    }

    public function testADeclaredPolicyIsShownAsItIsWrittenUnknownStayingUnknown(): void
    {
        $byPackage = [];
        foreach (self::directory()->providers() as $provider) {
            $byPackage[$provider->package] = $provider;
        }
        $insee = $byPackage['omnistate/insee']->policy;
        self::assertTrue($insee->declared);
        self::assertSame('none', $insee->access, 'no key');
        self::assertSame(30, $insee->limits[0]['max']);
        self::assertSame('minute', $insee->limits[0]['window']);
        self::assertSame('allowed', $insee->commercial);
        self::assertSame('unknown', $insee->caching, 'the terms do not address it: unknown, not allowed');
        self::assertSame('unknown', Policy::fact($insee->caching));
        self::assertSame('required', $insee->attribution);
        self::assertSame('maintainer', $insee->declaredBy);
        self::assertSame('2026-11-10', $insee->verifiedAt);
        // The schema says a key is needed; the site says how it is obtained.
        self::assertSame('oauth', $byPackage['omnical/google']->policy->access);
        self::assertSame('key', $byPackage['omnilang/deepl']->policy->access);
        self::assertSame('none', $byPackage['omnical/nager']->policy->access, 'a declarative provider declares its policy too');
        // A policy with plans (contracts' `plans`): the limits shown are the default plan's, each plan's kept.
        $azure = $byPackage['omnilang/azure']->policy;
        self::assertSame('f0', $azure->plan);
        self::assertContains('s1', $azure->plans);
        self::assertSame($azure->limits, $azure->planLimits['f0']);
        self::assertSame('characters', $azure->limits[0]['unit']);
        // A provider with no package: what the site read, its limits unknown.
        $adzuna = $byPackage['omnijob/adzuna']->policy;
        self::assertFalse($adzuna->declared);
        self::assertNull($adzuna->limits);
        self::assertSame('contract', $adzuna->commercial);
        self::assertSame('unknown', $adzuna->storage);
    }

    public function testTheReleasedProvidersAreCardsWithoutAPolicyUntilOneIsDeclared(): void
    {
        $omnibus = self::directory()->family('omnibus');
        self::assertNotNull($omnibus);
        self::assertGreaterThan(20, \count($omnibus->providers), 'the carriers, from catalog/omnibus.yaml');
        self::assertSame(Status::Available, $omnibus->providers[0]->status);
        self::assertSame('undeclared', $omnibus->providers[0]->getAuth());
        self::assertSame('undeclared', $omnibus->providers[0]->getCommercial());
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
        // Built on 2026-11-10: no longer reserved.
        self::assertSame(Status::Available, $directory->family('omnilang')->status);
        self::assertSame(Status::Available, $directory->family('omnical')->status);
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
        self::assertSame(Status::Available, $directory->family('omnicrawl')->status);
        foreach (['omnilang', 'omnical', 'omnicrawl'] as $built) {
            foreach ($directory->family($built)->providers as $provider) {
                self::assertSame(Status::Available, $provider->status, $provider->package);
                self::assertTrue($provider->policy?->declared, $provider->package.' declares its policy');
            }
        }
        $state = $statuses('omnistate');
        self::assertSame(['available', 'available', 'available'], [$state['omnistate/insee'], $state['omnistate/eurostat'], $state['omnistate/ecb']], 'statistics: the capability omnistate gained');
    }

    public function testATypedPolicyRefusesAValueItDoesNotKnow(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        Policy::fromTyped(['auth' => 'password']);
    }

    public function testAReleasedProviderIsNeverTypedAPolicy(): void
    {
        $root = \dirname(__DIR__, 2);
        $file = tempnam(sys_get_temp_dir(), 'apis');
        file_put_contents($file, <<<'YAML'
            capabilities:
              audience: { en: Audience, fr: Audience }
            families:
              omnipulse:
                status: available
                capabilities: [audience]
                providers:
                  omnipulse/matomo:
                    status: available
                    policy: { auth: none, commercial: yes, attribution: none, terms: 'https://matomo.org', read: '2026-11-10' }
            YAML);
        try {
            $this->expectExceptionMessage('omnipulse/matomo is released: its policy is its package\'s policy.json');
            (new Directory(new Catalogue($root.'/catalog'), new DeclaredPolicies($root.'/tests/Fixtures/none.json'), $file))->families();
        } finally {
            unlink($file);
        }
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
