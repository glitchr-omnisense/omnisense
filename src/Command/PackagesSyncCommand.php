<?php

namespace App\Command;

use App\Catalogue\Synchronizer;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Lock\LockFactory;

/**
 * `bin/console app:packages:sync`: the catalogue read again from the
 * repositories (App\Catalogue\Synchronizer). Run from cron each night, and
 * by hand after a package is added or renamed.
 *
 *   --offline   do not ask Packagist and npm: what they last said is kept
 *   --fresh     ask them again now, whatever the cache holds
 */
#[AsCommand(name: 'app:packages:sync', description: 'Reads the catalogue again from the packages\' repositories, Packagist and npm')]
class PackagesSyncCommand extends Command
{
    public function __construct(
        private readonly Synchronizer $synchronizer,
        private readonly LockFactory $locks,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('offline', null, InputOption::VALUE_NONE, 'Do not ask the registries')
            ->addOption('fresh', null, InputOption::VALUE_NONE, 'Ask the registries again, whatever the cache holds');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        // One run at a time: cron and a hand at the same moment would write the same rows.
        $lock = $this->locks->createLock('app-packages-sync', 600);
        if (!$lock->acquire()) {
            $io->warning('A synchronisation is already running.');

            return Command::FAILURE;
        }

        try {
            $report = $this->synchronizer->sync(
                registries: !$input->getOption('offline'),
                fresh: (bool) $input->getOption('fresh'),
                progress: $output->isVerbose() ? static fn (string $name, string $what) => $io->writeln(sprintf('  %-9s %s', $what, $name)) : null,
            );
        } finally {
            $lock->release();
        }

        $io->definitionList(
            ['Packages' => $report->total()],
            ['Created' => \count($report->created)],
            ['Updated' => \count($report->updated)],
            ['Unchanged' => $report->unchanged],
            ['No longer found' => \count($report->missing)],
            ['Sites read ("used by")' => $report->sites],
            ['Registries' => $input->getOption('offline') ? 'not asked (--offline)' : sprintf('%d answered, %d unreachable', $report->registryAnswered, $report->registryUnreachable)],
        );
        if ($report->registryUnreachable > 0) {
            $io->note('A registry did not answer for some packages: what it last said about them is kept.');
        }
        if (0 === $report->total()) {
            $io->warning('No package was found: are the repositories mounted (OMNI_REPOS)?');

            return Command::FAILURE;
        }
        $io->success('The catalogue is up to date.');

        return Command::SUCCESS;
    }
}
