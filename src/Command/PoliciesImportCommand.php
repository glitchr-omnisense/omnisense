<?php

namespace App\Command;

use App\Catalogue\Api\PolicyImporter;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Attribute\Option;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(name: 'app:policies:import', description: 'Write config/policies.json: the usage policy each provider of the catalogue declares (policy.json), read at its published head and checked against omnisense/contracts\' schema')]
final class PoliciesImportCommand
{
    public function __construct(private readonly PolicyImporter $importer)
    {
    }

    public function __invoke(SymfonyStyle $io, #[Option(description: 'Read the working copies under OMNI_REPOS at refs/remotes/origin/<branch>, without the network')] bool $local = false): int
    {
        $report = $this->importer->import($local);
        if ([] !== $report['errors']) {
            $io->error(\sprintf("%d policies do not validate, config/policies.json left as it was:\n%s", \count($report['errors']), implode("\n", $report['errors'])));

            return 1;
        }
        if ($report['kept']) {
            $io->warning('Not read, their entry kept as it was: '.implode(', ', $report['kept']));
        }
        $io->success(\sprintf('%d providers, %d policies declared.', $report['packages'], $report['policies']));

        return 0;
    }
}
