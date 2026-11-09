<?php

namespace App\Command;

use App\Site\Stars;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Yaml\Yaml;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * The header's star count, read once at build time from GitHub's REST API
 * (anonymously, or with GITHUB_TOKEN when the build has one) into
 * config/github.yaml. A failure keeps the file as it was: the build never
 * depends on it, and a page without a count shows the button alone.
 */
#[AsCommand(name: 'app:stars:fetch', description: 'Write the site repository\'s star count from GitHub into config/github.yaml; GITHUB_TOKEN lifts the rate limit')]
final class StarsFetchCommand
{
    public function __construct(
        private readonly HttpClientInterface $http,
        private readonly Stars $stars,
        #[Autowire('%app.github_file%')] private readonly string $file,
        #[Autowire('%env(default::GITHUB_TOKEN)%')] private readonly ?string $token = null,
    ) {
    }

    public function __invoke(SymfonyStyle $io): int
    {
        $headers = ['Accept' => 'application/vnd.github+json', 'X-GitHub-Api-Version' => '2022-11-28', 'User-Agent' => 'omnisense-website'];
        if (null !== $this->token && '' !== $this->token) {
            $headers['Authorization'] = 'Bearer '.$this->token;
        }

        try {
            $data = $this->http->request('GET', 'https://api.github.com/repos/'.$this->stars->getRepository(), ['headers' => $headers, 'timeout' => 10])->toArray();
        } catch (\Throwable $e) {
            $io->warning('GitHub did not answer, config/github.yaml kept as it was: '.$e->getMessage());

            return 0;
        }

        $count = $data['stargazers_count'] ?? null;
        if (!\is_int($count)) {
            $io->warning('No star count in GitHub\'s answer, config/github.yaml kept as it was.');

            return 0;
        }

        file_put_contents($this->file, "# The header's star count, written by app:stars:fetch at build time (App\\Site\\Stars).\n".Yaml::dump(['repository' => $this->stars->getRepository(), 'stars' => $count]));
        $io->success(\sprintf('%s: %d stars.', $this->stars->getRepository(), $count));

        return 0;
    }
}
