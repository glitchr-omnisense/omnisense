<?php

namespace App\Command;

use App\Catalogue\CatalogueImporter;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Attribute\Option;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(name: 'app:catalog:import', description: 'Write catalog/*.yaml and config/documentation.yaml from the repositories under OMNI_REPOS')]
final class CatalogImportCommand
{
    public function __construct(private readonly CatalogueImporter $importer)
    {
    }

    public function __invoke(SymfonyStyle $io, #[Option(description: 'Move every documentation series to its branch\'s published head')] bool $pin = false): int
    {
        $report = $this->importer->import($pin);
        $io->success(sprintf('%d families, %d packages; %d documented, %d series pinned.', $report['families'], $report['packages'], $report['documented'], $report['pinned']));
        if ($report['skipped']) {
            $io->note('Not on GitHub, left out: '.implode(', ', $report['skipped']));
        }

        return 0;
    }
}
