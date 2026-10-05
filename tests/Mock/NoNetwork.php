<?php

namespace App\Tests\Mock;

use Symfony\Component\HttpClient\Response\MockResponse;

/**
 * The outside world as the tests see it (framework.http_client
 * .mock_response_factory, test env): no request ever leaves the machine,
 * and each one asked for is kept, so a test can say what was called.
 *
 * A test scripts an answer in $answers, by the start of the URL (Packagist,
 * npm, GitHub). Anything else is a 503: a service that does not answer.
 */
final class NoNetwork
{
    /** @var list<string> "METHOD url" of every request made */
    public static array $requests = [];

    /** @var array<string, array{0: array<mixed>, 1?: int}> URL prefix => [JSON body, HTTP code] */
    public static array $answers = [];

    public static function reset(): void
    {
        self::$requests = [];
        self::$answers = [];
    }

    public function __invoke(string $method, string $url, array $options = []): MockResponse
    {
        self::$requests[] = $method.' '.$url;
        foreach (self::$answers as $prefix => $answer) {
            if (str_starts_with($url, $prefix)) {
                return new MockResponse(json_encode($answer[0]), ['http_code' => $answer[1] ?? 200, 'response_headers' => ['content-type' => 'application/json']]);
            }
        }

        return new MockResponse(json_encode(['error' => 'No network in the tests: '.$method.' '.$url]), ['http_code' => 503]);
    }
}
