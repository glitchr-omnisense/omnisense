<?php

namespace App\Sponsor;

use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * The organisation's GitHub Sponsors page, read with one GraphQL call:
 * whether it is open, its tiers, its goal, its active sponsors.
 *
 * It needs a token (GITHUB_SPONSORS_TOKEN: a read-only one of an account
 * that manages the organisation's sponsorships). Without one GitHub is not
 * asked and the answer is "unconfigured": the Support page then says there
 * is no data, and shows none. An answer is kept an hour.
 *
 * One provider, so no family (plan 0, rule 2): if OpenCollective or
 * Liberapay come one day, this becomes one of several implementations.
 */
class GitHubSponsors
{
    private const ENDPOINT = 'https://api.github.com/graphql';
    private const TTL = 3600;

    private const QUERY = <<<'GRAPHQL'
        query ($login: String!) {
          organization(login: $login) {
            hasSponsorsListing
            sponsorsListing {
              url
              shortDescription
              activeGoal { title description kind percentComplete targetValue }
              tiers(first: 30) { nodes { name description monthlyPriceInDollars isOneTime isCustomAmount } }
            }
            sponsorshipsAsMaintainer(first: 100, activeOnly: true, includePrivate: true) {
              totalCount
              nodes {
                privacyLevel
                tier { name }
                sponsorEntity {
                  ... on User { login name avatarUrl url }
                  ... on Organization { login name avatarUrl url }
                }
              }
            }
          }
        }
        GRAPHQL;

    public function __construct(
        private readonly HttpClientInterface $http,
        private readonly CacheInterface $cache,
        #[Autowire('%env(GITHUB_SPONSORS_TOKEN)%')] #[\SensitiveParameter] private readonly string $token,
        #[Autowire('%env(GITHUB_SPONSORS_LOGIN)%')] private readonly string $login,
        private readonly ?LoggerInterface $logger = null,
    ) {
    }

    public function isConfigured(): bool
    {
        return '' !== trim($this->token);
    }

    public function getLogin(): string
    {
        return $this->login;
    }

    /** Never throws: a failure is a state of the answer. */
    public function fetch(bool $fresh = false): Sponsors
    {
        if (!$this->isConfigured()) {
            return new Sponsors(Sponsors::UNCONFIGURED, $this->login);
        }

        $key = 'app.sponsors.'.md5($this->login);
        if ($fresh) {
            $this->cache->delete($key);
        }

        try {
            return $this->cache->get($key, function (ItemInterface $item): Sponsors {
                $item->expiresAfter(self::TTL);

                return $this->ask();
            });
        } catch (\Throwable $e) {
            // Not cached: the next page asks again.
            $this->logger?->notice('GitHub Sponsors did not answer: {message}', ['message' => $e->getMessage()]);

            return new Sponsors(Sponsors::UNREACHABLE, $this->login, reason: $e->getMessage());
        }
    }

    private function ask(): Sponsors
    {
        $response = $this->http->request('POST', self::ENDPOINT, [
            'timeout' => 8,
            'headers' => ['Authorization' => 'Bearer '.$this->token, 'User-Agent' => 'omniscient', 'Accept' => 'application/vnd.github+json'],
            'json' => ['query' => self::QUERY, 'variables' => ['login' => $this->login]],
        ]);
        if (200 !== $status = $response->getStatusCode()) {
            throw new \RuntimeException('GitHub answered '.$status);
        }
        $body = $response->toArray();
        if (!empty($body['errors'])) {
            throw new \RuntimeException((string) ($body['errors'][0]['message'] ?? 'GraphQL error'));
        }
        $organization = $body['data']['organization'] ?? null;
        if (!\is_array($organization)) {
            throw new \RuntimeException(sprintf('No organisation "%s".', $this->login));
        }

        $now = new \DateTimeImmutable();
        $listing = $organization['sponsorsListing'] ?? null;
        if (empty($organization['hasSponsorsListing']) || !\is_array($listing)) {
            return new Sponsors(Sponsors::CLOSED, $this->login, at: $now);
        }

        $tiers = [];
        foreach ($listing['tiers']['nodes'] ?? [] as $tier) {
            if (!empty($tier['isCustomAmount'])) {
                continue;
            }
            $tiers[] = ['name' => (string) $tier['name'], 'description' => $tier['description'] ?? null, 'monthly' => (int) ($tier['monthlyPriceInDollars'] ?? 0), 'oneTime' => !empty($tier['isOneTime'])];
        }
        usort($tiers, static fn (array $a, array $b): int => [$a['oneTime'], $a['monthly']] <=> [$b['oneTime'], $b['monthly']]);

        $sponsors = [];
        $sponsorships = $organization['sponsorshipsAsMaintainer'] ?? [];
        foreach ($sponsorships['nodes'] ?? [] as $sponsorship) {
            $entity = $sponsorship['sponsorEntity'] ?? null;
            // A private sponsorship is counted and never named.
            if ('PUBLIC' !== ($sponsorship['privacyLevel'] ?? null) || !\is_array($entity) || empty($entity['login'])) {
                continue;
            }
            $sponsors[] = ['login' => (string) $entity['login'], 'name' => $entity['name'] ?? null, 'avatar' => $entity['avatarUrl'] ?? null, 'url' => $entity['url'] ?? null, 'tier' => $sponsorship['tier']['name'] ?? null];
        }

        $goal = $listing['activeGoal'] ?? null;

        return new Sponsors(
            state: Sponsors::OPEN,
            login: $this->login,
            url: $listing['url'] ?? 'https://github.com/sponsors/'.$this->login,
            description: $listing['shortDescription'] ?? null,
            tiers: $tiers,
            sponsors: $sponsors,
            total: (int) ($sponsorships['totalCount'] ?? \count($sponsors)),
            goal: \is_array($goal) ? ['title' => $goal['title'] ?? null, 'description' => $goal['description'] ?? null, 'percent' => (int) ($goal['percentComplete'] ?? 0), 'target' => isset($goal['targetValue']) ? (int) $goal['targetValue'] : null, 'kind' => $goal['kind'] ?? null] : null,
            at: $now,
        );
    }
}
