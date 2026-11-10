<?php

namespace App\Tests\Unit;

use App\Catalogue\Api\DeclaredPolicies;
use App\Catalogue\Api\PolicyImporter;
use App\Catalogue\Catalogue;
use App\Catalogue\RepositoryScanner;
use Omnisense\Contracts\Policy\InvalidPolicyException;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpClient\MockHttpClient;

/**
 * The usage policies the providers' packages declare: config/policies.json
 * as app:policies:import writes it (omnisense/contracts' export) and as the
 * site reads it - each policy checked against the schema, so that one that
 * does not validate fails the build.
 */
final class PoliciesTest extends TestCase
{
    private const ROOT = __DIR__.'/../..';

    public function testTheCommittedPoliciesValidateAndBelongToTheCatalogue(): void
    {
        $catalogue = new Catalogue(self::ROOT.'/catalog');
        $policies = (new DeclaredPolicies(self::ROOT.'/config/policies.json'))->all();
        self::assertGreaterThanOrEqual(80, \count($policies));
        foreach ($policies as $package => $entry) {
            self::assertNotNull($catalogue->find($package), $package.' is a package of the catalogue');
            self::assertMatchesRegularExpression('/^[0-9a-f]{40}$/', (string) $entry['ref'], $package.' was read at a commit');
        }
        foreach (['omnical/nager', 'omnilang/deepl', 'omnicrawl/zyte', 'omnistate/eurostat', 'omnitravel/transitous'] as $package) {
            self::assertArrayHasKey($package, $policies);
        }
    }

    public function testAPolicyTheSchemaRefusesFailsAsTheFileIsRead(): void
    {
        $file = tempnam(sys_get_temp_dir(), 'policies');
        file_put_contents($file, json_encode(['policies' => [['package' => 'omnitrade/stripe', 'file' => 'policy.json', 'policy' => ['provider' => 'stripe', 'rights' => ['commercial' => 'maybe']]]]]));
        try {
            $this->expectException(InvalidPolicyException::class);
            $this->expectExceptionMessage('omnitrade/stripe\'s policy.json does not validate');
            (new DeclaredPolicies($file))->all();
        } finally {
            unlink($file);
        }
    }

    public function testAnEntryTheExportCouldNotReadFailsToo(): void
    {
        $file = tempnam(sys_get_temp_dir(), 'policies');
        file_put_contents($file, json_encode(['policies' => [['package' => 'omnitrade/stripe', 'file' => 'policy.json', 'error' => 'not JSON']]]));
        try {
            $this->expectException(InvalidPolicyException::class);
            (new DeclaredPolicies($file))->all();
        } finally {
            unlink($file);
        }
    }

    public function testTheImportExportsEachDeclaredPolicy(): void
    {
        $out = sys_get_temp_dir().'/policies-'.bin2hex(random_bytes(4)).'.json';
        $importer = new PolicyImporter(new Catalogue(self::ROOT.'/tests/Fixtures/catalog'), new RepositoryScanner(self::ROOT.'/tests/Fixtures/repos'), new MockHttpClient(), $out);
        $report = $importer->import(local: true);
        try {
            self::assertTrue($report['written']);
            self::assertSame([], $report['errors']);
            self::assertSame(1, $report['policies'], 'omnitrade/stripe declares one; omnitrade/paypal none');
            $read = (new DeclaredPolicies($out))->of('omnitrade/stripe');
            self::assertNotNull($read);
            self::assertSame('stripe', $read['policy']->provider);
            self::assertSame(100, $read['policy']->rateLimits[0]->max);
            self::assertNull((new DeclaredPolicies($out))->of('omnitrade/paypal'));
        } finally {
            @unlink($out);
        }
    }

    public function testAPolicyThatDoesNotValidateFailsTheImportAndNothingIsWritten(): void
    {
        $fs = new Filesystem();
        $repos = sys_get_temp_dir().'/repos-'.bin2hex(random_bytes(4));
        $fs->mirror(self::ROOT.'/tests/Fixtures/repos/omnitrade', $repos.'/omnitrade');
        $fs->dumpFile($repos.'/omnitrade/stripe/policy.json', '{"provider": "stripe", "rateLimits": [{"max": 10, "window": "fortnight"}]}');
        $out = $repos.'/policies.json';
        try {
            $report = (new PolicyImporter(new Catalogue(self::ROOT.'/tests/Fixtures/catalog'), new RepositoryScanner($repos), new MockHttpClient(), $out))->import(local: true);
            self::assertFalse($report['written']);
            self::assertCount(1, $report['errors']);
            self::assertStringContainsString('omnitrade/stripe', $report['errors'][0]);
            self::assertStringContainsString('rateLimits[0].window', $report['errors'][0], 'the error names its path');
            self::assertFileDoesNotExist($out);
        } finally {
            $fs->remove($repos);
        }
    }
}
