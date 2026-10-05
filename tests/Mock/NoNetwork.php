<?php

namespace App\Tests\Mock;

use Symfony\Component\HttpClient\Response\MockResponse;

/**
 * The outside world as the tests see it (framework.http_client
 * .mock_response_factory, test env): no request ever leaves the machine,
 * and each one asked for is kept, so a test can say what was called.
 *
 * What the real sources answered to the calls the tests make was recorded
 * once (tests/Fixtures/record.php, on the day each file of tests/Fixtures/network
 * names in its request) - EUR-Lex, the administrative courts' open data,
 * HAL - and is replayed for the same request; the decisions' full texts
 * were cut to their first lines. A test may also script an answer in
 * $answers, by the start of the URL. Anything else is a 503: a source that
 * does not answer.
 */
final class NoNetwork
{
    /** @var list<string> "METHOD url" of every request made */
    public static array $requests = [];

    /** @var array<string, array{0: array<mixed>, 1?: int}> URL prefix => [JSON body, HTTP code] */
    public static array $answers = [];

    /** Whether the recorded answers are replayed (off: every source is down). */
    public static bool $recorded = true;

    /** @var array<string, array{status: int, type: string, body: string}>|null */
    private static ?array $records = null;

    public function __invoke(string $method, string $url, array $options = []): MockResponse
    {
        self::$requests[] = $method.' '.$url;
        foreach (self::$answers as $prefix => $answer) {
            if (str_starts_with($url, $prefix)) {
                return new MockResponse(json_encode($answer[0]), ['http_code' => $answer[1] ?? 200, 'response_headers' => ['content-type' => 'application/json']]);
            }
        }
        if (self::$recorded && null !== $record = self::records()[self::key($method.' '.$url)] ?? null) {
            return new MockResponse($record['body'], ['http_code' => $record['status'], 'response_headers' => ['content-type' => $record['type']]]);
        }

        return new MockResponse(json_encode(['error' => 'No network in the tests: '.$method.' '.$url]), ['http_code' => 503]);
    }

    /** @return array<string, array{status: int, type: string, body: string}> */
    private static function records(): array
    {
        if (null === self::$records) {
            self::$records = [];
            foreach (glob(\dirname(__DIR__).'/Fixtures/network/*.json') ?: [] as $file) {
                $record = json_decode((string) file_get_contents($file), true);
                self::$records[self::key($record['request'])] = $record;
            }
        }

        return self::$records;
    }

    /** The same request whatever way its query was encoded. */
    private static function key(string $request): string
    {
        return rawurldecode(str_replace('+', '%20', $request));
    }
}
