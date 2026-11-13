<?php

namespace App\Command;

use App\Site\Provenance;
use Symfony\Component\Console\Attribute\Argument;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Attribute\Option;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Writes <folder>/provenance.json, the deployment manifest (App\Site\Provenance),
 * from the CI's variables (GITHUB_SHA, GITHUB_REF, GITHUB_RUN_ID...) or, on a
 * maintainer's machine, from the working copy. The website's workflow runs it
 * once the export is checked and compared, before it publishes the folder.
 */
#[AsCommand(name: 'app:provenance', description: 'Write the deployment manifest (provenance.json) into an export\'s folder: the site\'s commit, each documentation source\'s, the packages and tools, the checksums')]
final class ProvenanceCommand
{
    private const VARIABLES = ['GITHUB_SHA', 'GITHUB_REF', 'GITHUB_REPOSITORY', 'GITHUB_SERVER_URL', 'GITHUB_RUN_ID', 'GITHUB_RUN_ATTEMPT', 'GITHUB_EVENT_NAME'];

    public function __construct(private readonly Provenance $provenance)
    {
    }

    public function __invoke(
        SymfonyStyle $io,
        #[Argument(description: 'The export\'s folder')] string $folder = 'dist',
        #[Option(description: 'The export\'s last line (its counts: rendered, reused, written...)')] ?string $summary = null,
        #[Option(description: 'What asked for the build beyond its event, as JSON (a dispatch\'s payload)')] ?string $request = null,
    ): int {
        $environment = [];
        foreach (self::VARIABLES as $name) {
            $value = $_SERVER[$name] ?? getenv($name);
            $environment[$name] = \is_string($value) ? $value : null;
        }
        $asked = null;
        if (null !== $request && '' !== $request) {
            $asked = json_decode($request, true);
            if (!\is_array($asked)) {
                $io->error('--request is not a JSON object.');

                return 1;
            }
        }

        try {
            $data = $this->provenance->write($folder, $environment, $summary, $asked);
        } catch (\InvalidArgumentException $e) {
            $io->error($e->getMessage());

            return 1;
        }

        $io->success(\sprintf('%s/%s: site %s, %d documentation sources, %d packages, %d files (%s).', rtrim($folder, '/'), Provenance::FILE, $data['site']['commit'] ?? '?', array_sum(array_map('count', $data['documentation'])), \count($data['packages']), $data['artifact']['files'], $data['artifact']['sha256']));

        return 0;
    }
}
