<?php

namespace App\Command;

use App\Content\DocsFetcher;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Attribute\Option;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(name: 'app:docs:fetch', description: 'Fetch each package\'s docs/ folder at the ref config/documentation.yaml pins, into var/docs')]
final class DocsFetchCommand
{
    public function __construct(private readonly DocsFetcher $fetcher)
    {
    }

    public function __invoke(
        SymfonyStyle $io,
        #[Option(description: 'A folder of working copies to read the commits from instead of GitHub (each found by its origin)')] ?string $local = null,
        #[Option(description: 'Only this manual (its key)')] ?string $only = null,
    ): int {
        $report = $this->fetcher->fetch($local, static function (string $manual, string $series, string $what) use ($io): void {
            if ($io->isVerbose() || !\in_array($what, ['kept', 'fetched'], true)) {
                $io->writeln(sprintf('  %s %s: %s', $manual, $series, $what), OutputInterface::VERBOSITY_NORMAL);
            }
        }, $only);

        $io->success(sprintf('%d series fetched, %d already there.', $report['fetched'], $report['kept']));
        if ($report['failed']) {
            $io->error(sprintf('%d series could not be fetched.', \count($report['failed'])));

            return 1;
        }

        return 0;
    }
}
