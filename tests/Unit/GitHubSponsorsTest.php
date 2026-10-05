<?php

namespace App\Tests\Unit;

use App\Sponsor\GitHubSponsors;
use App\Sponsor\Sponsors;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

/** GitHub Sponsors on a MockHttpClient: asked only with a token, and a failure is a state, never an invention. */
final class GitHubSponsorsTest extends TestCase
{
    /** Manifestly not a real token. */
    private const TOKEN = 'ghp_test_not_a_real_token';

    public function testWithoutATokenGitHubIsNotAsked(): void
    {
        $calls = 0;
        $github = new GitHubSponsors(new MockHttpClient(function () use (&$calls): MockResponse {
            ++$calls;

            return new MockResponse('{}');
        }), new ArrayAdapter(), '', 'glitchr-studio');

        $sponsors = $github->fetch();

        self::assertFalse($github->isConfigured());
        self::assertSame(Sponsors::UNCONFIGURED, $sponsors->state);
        self::assertFalse($sponsors->isOpen());
        self::assertSame([], $sponsors->sponsors);
        self::assertSame([], $sponsors->tiers);
        self::assertNull($sponsors->goal);
        self::assertSame(0, $calls);
    }

    public function testAnOpenPageItsTiersItsGoalItsSponsors(): void
    {
        $request = null;
        $github = new GitHubSponsors(new MockHttpClient(function (string $method, string $url, array $options) use (&$request): MockResponse {
            $request = [$method, $url, $options];

            return new MockResponse(json_encode(['data' => ['organization' => [
                'hasSponsorsListing' => true,
                'sponsorsListing' => [
                    'url' => 'https://github.com/sponsors/glitchr-studio',
                    'shortDescription' => 'Packages for Symfony sites.',
                    'activeGoal' => ['title' => '10 sponsors', 'description' => null, 'kind' => 'TOTAL_SPONSORS_COUNT', 'percentComplete' => 30, 'targetValue' => 10],
                    'tiers' => ['nodes' => [
                        ['name' => '$25 a month', 'description' => 'A name on the wall.', 'monthlyPriceInDollars' => 25, 'isOneTime' => false, 'isCustomAmount' => false],
                        ['name' => '$5 a month', 'description' => 'Thanks.', 'monthlyPriceInDollars' => 5, 'isOneTime' => false, 'isCustomAmount' => false],
                        ['name' => 'Custom', 'description' => null, 'monthlyPriceInDollars' => 0, 'isOneTime' => true, 'isCustomAmount' => true],
                    ]],
                ],
                'sponsorshipsAsMaintainer' => ['totalCount' => 3, 'nodes' => [
                    ['privacyLevel' => 'PUBLIC', 'tier' => ['name' => '$5 a month'], 'sponsorEntity' => ['login' => 'example-user', 'name' => 'An Example', 'avatarUrl' => 'https://avatars.example/1', 'url' => 'https://github.com/example-user']],
                    ['privacyLevel' => 'PRIVATE', 'tier' => ['name' => '$25 a month'], 'sponsorEntity' => ['login' => 'hidden-user', 'name' => 'Hidden', 'avatarUrl' => null, 'url' => 'https://github.com/hidden-user']],
                    ['privacyLevel' => 'PUBLIC', 'tier' => null, 'sponsorEntity' => ['login' => 'example-org', 'name' => null, 'avatarUrl' => null, 'url' => 'https://github.com/example-org']],
                ]],
            ]]]));
        }), $cache = new ArrayAdapter(), self::TOKEN, 'glitchr-studio');

        $sponsors = $github->fetch();

        self::assertSame(['POST', 'https://api.github.com/graphql'], [$request[0], $request[1]]);
        self::assertContains('Authorization: Bearer '.self::TOKEN, $request[2]['headers']);
        self::assertStringContainsString('"login":"glitchr-studio"', $request[2]['body']);
        self::assertStringContainsString('sponsorshipsAsMaintainer', $request[2]['body']);

        self::assertTrue($sponsors->isOpen());
        self::assertSame('https://github.com/sponsors/glitchr-studio', $sponsors->url);
        self::assertSame(['$5 a month', '$25 a month'], array_column($sponsors->tiers, 'name'), 'by price, the custom amount left out');
        self::assertSame(['title' => '10 sponsors', 'description' => null, 'percent' => 30, 'target' => 10, 'kind' => 'TOTAL_SPONSORS_COUNT'], $sponsors->goal);
        // A private sponsorship is counted, never named.
        self::assertSame(3, $sponsors->total);
        self::assertSame(['example-user', 'example-org'], array_column($sponsors->sponsors, 'login'));
        self::assertSame(1, $sponsors->getPrivateCount());
        self::assertStringNotContainsString('hidden-user', serialize($sponsors));
    }

    public function testNoSponsorsPageIsSaidNotGuessed(): void
    {
        $github = new GitHubSponsors(new MockHttpClient(new MockResponse(json_encode(['data' => ['organization' => ['hasSponsorsListing' => false, 'sponsorsListing' => null, 'sponsorshipsAsMaintainer' => ['totalCount' => 0, 'nodes' => []]]]]))), new ArrayAdapter(), self::TOKEN, 'glitchr-studio');

        self::assertSame(Sponsors::CLOSED, $github->fetch()->state);
    }

    public function testASilentOrRefusingGitHubIsUnreachableAndAskedAgain(): void
    {
        $calls = 0;
        $answers = [new MockResponse('', ['http_code' => 502]), new MockResponse(json_encode(['errors' => [['message' => 'Bad credentials']]])), new MockResponse(json_encode(['data' => ['organization' => ['hasSponsorsListing' => false]]]))];
        $github = new GitHubSponsors(new MockHttpClient(function () use (&$calls, $answers): MockResponse {
            return $answers[$calls++];
        }), new ArrayAdapter(), self::TOKEN, 'glitchr-studio');

        $first = $github->fetch();
        self::assertSame(Sponsors::UNREACHABLE, $first->state);
        self::assertSame([], $first->sponsors);
        $second = $github->fetch();
        self::assertSame([Sponsors::UNREACHABLE, 'Bad credentials'], [$second->state, $second->reason]);
        // The failure was not kept: the third call gets the answer, and that one is.
        self::assertSame(Sponsors::CLOSED, $github->fetch()->state);
        self::assertSame(Sponsors::CLOSED, $github->fetch()->state);
        self::assertSame(3, $calls);
    }
}
