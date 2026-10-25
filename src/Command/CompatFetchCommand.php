<?php

namespace App\Command;

use App\Catalogue\CompatibilityFetcher;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(name: 'app:compat:fetch', description: 'Write the compatibility dashboard\'s data from GitHub (CI runs and jobs, conformance suites); GITHUB_TOKEN lifts the rate limit')]
final class CompatFetchCommand
{
    public function __construct(private readonly CompatibilityFetcher $fetcher)
    {
    }

    public function __invoke(SymfonyStyle $io): int
    {
        $report = $this->fetcher->fetch();
        $io->success(sprintf('%d packages read from GitHub of %d; %d run a conformance suite.', $report['fetched'], $report['packages'], $report['suites']));
        if ($report['stale']) {
            $io->warning('Not answered, kept as they were: '.implode(', ', $report['stale']));
        }

        return 0;
    }
}
