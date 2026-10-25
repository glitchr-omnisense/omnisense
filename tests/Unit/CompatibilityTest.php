<?php

namespace App\Tests\Unit;

use App\Catalogue\Catalogue;
use App\Catalogue\Compatibility;
use App\Catalogue\CompatibilityFetcher;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;
use Symfony\Component\Yaml\Yaml;

/** The compatibility dashboard's data: read from GitHub's answers (recorded here), joined with the catalogue. */
final class CompatibilityTest extends TestCase
{
    private string $file;

    protected function setUp(): void
    {
        $this->file = sys_get_temp_dir().'/compat-'.bin2hex(random_bytes(4)).'.yaml';
    }

    protected function tearDown(): void
    {
        @unlink($this->file);
    }

    public function testRunsJobsAndTheConformanceSuiteAreReadFromGitHub(): void
    {
        $seen = [];
        $http = new MockHttpClient(function (string $method, string $url, array $options) use (&$seen): MockResponse {
            $seen[] = $url;
            $json = static fn (array $data): MockResponse => new MockResponse((string) json_encode($data));
            if (str_contains($url, '/actions/runs?')) {
                return match (true) {
                    str_contains($url, '/omnitrade-stripe/') => $json(['workflow_runs' => [
                        ['id' => 11, 'name' => 'release', 'event' => 'push', 'conclusion' => 'success', 'head_sha' => 'aaa'],
                        ['id' => 12, 'name' => 'ci', 'event' => 'push', 'conclusion' => 'success', 'head_sha' => 'bbb', 'html_url' => 'https://github.com/glitchr-studio/omnitrade-stripe/actions/runs/12', 'updated_at' => '2026-10-25T10:00:00Z'],
                        ['id' => 13, 'name' => 'ci', 'event' => 'push', 'conclusion' => 'failure', 'head_sha' => 'ccc'],
                    ]]),
                    str_contains($url, '/omnitrade-paypal/') => new MockResponse('{"message": "API rate limit exceeded"}', ['http_code' => 403]),
                    default => $json(['workflow_runs' => []]),
                };
            }
            if (str_contains($url, '/actions/runs/12/jobs')) {
                return $json(['jobs' => [['name' => 'package / PHP 8.4', 'conclusion' => 'success']]]);
            }
            if (str_contains($url, '/git/trees/')) {
                return $json(str_contains($url, '/omnitrade-stripe/')
                    ? ['sha' => 'bbb', 'tree' => [['path' => 'Tests/StripeGatewayTest.php'], ['path' => 'Tests/StripeContractTest.php'], ['path' => 'Api.php']]]
                    : ['sha' => 'ddd', 'tree' => []]);
            }
            if ('https://raw.githubusercontent.com/glitchr-studio/omnitrade-stripe/bbb/Tests/StripeContractTest.php' === $url) {
                return new MockResponse("<?php\n\nnamespace Omnitrade\\Stripe\\Tests;\n\nuse Omnitrade\\Testing\\GatewayContractTests;\n\nfinal class StripeContractTest extends TestCase\n{\n    use GatewayContractTests;\n}\n");
            }

            return new MockResponse('', ['http_code' => 404]);
        });
        $catalogue = new Catalogue(\dirname(__DIR__).'/Fixtures/catalog');
        file_put_contents($this->file, Yaml::dump(['packages' => ['omnitrade/paypal' => ['repository' => 'glitchr-studio/omnitrade-paypal', 'branch' => '1.x', 'ci' => [], 'conformance' => ['suite' => 'Omnitrade\Testing\GatewayContractTests', 'test' => 'Tests/PayPalContractTest.php']]]]));

        $report = (new CompatibilityFetcher($http, $catalogue, $this->file, 'token'))->fetch(new \DateTimeImmutable('2026-10-25T12:00:00Z'));

        self::assertSame(['omnitrade/paypal'], $report['stale'], 'a rate limit keeps what the file held');
        $data = Yaml::parseFile($this->file);
        self::assertSame('2026-10-25T12:00:00+00:00', $data['generated']);
        self::assertSame([[
            'workflow' => 'ci',
            'url' => 'https://github.com/glitchr-studio/omnitrade-stripe/actions/runs/12',
            'head' => 'bbb',
            'at' => '2026-10-25T10:00:00Z',
            'conclusion' => 'success',
            'jobs' => [['label' => 'PHP 8.4', 'conclusion' => 'success']],
        ]], $data['packages']['omnitrade/stripe']['ci'], 'the last finished ci run, the release left out');
        self::assertSame(['suite' => 'Omnitrade\Testing\GatewayContractTests', 'test' => 'Tests/StripeContractTest.php'], $data['packages']['omnitrade/stripe']['conformance']);
        self::assertTrue($data['packages']['omnitrade/paypal']['stale']);
        self::assertContains('https://raw.githubusercontent.com/glitchr-studio/omnitrade-stripe/bbb/Tests/StripeContractTest.php', $seen, 'read at the tree\'s commit');

        $rows = [];
        foreach ((new Compatibility($catalogue, $this->file))->rows() as $row) {
            $rows[$row['package']->getName()] = $row;
        }
        self::assertSame('passes', $rows['omnitrade/stripe']['conformance']);
        self::assertSame(['PHP 8.4' => 'success'], $rows['omnitrade/stripe']['jobs']);
        self::assertSame('unknown', $rows['omnitrade/paypal']['conformance'], 'the suite runs, no CI run read');
        self::assertSame('none', $rows['omniscient/omnitrade']['conformance'], 'not a provider');
        self::assertSame(['PHP 8.4'], (new Compatibility($catalogue, $this->file))->labels());
    }
}
