<?php

namespace App\Command;

use App\Catalogue\RepositoryCloner;
use Symfony\Component\Console\Attribute\Argument;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Attribute\Option;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * The working copies app:catalog:import needs, where there are none (the website workflow's package-released
 * dispatch): `app:catalog:clone var/repos --repository=<owner/name>`, then `OMNI_REPOS=var/repos app:catalog:import`.
 */
#[AsCommand(name: 'app:catalog:clone', description: 'Clone (shallow) every repository the catalogue lists, and the ones named, into a new folder that app:catalog:import can read (OMNI_REPOS)')]
final class CatalogCloneCommand
{
    public function __construct(private readonly RepositoryCloner $cloner)
    {
    }

    /**
     * @param list<string> $repository
     */
    public function __invoke(
        SymfonyStyle $io,
        #[Argument(description: 'The folder the clones go into (as OMNI_REPOS)')] string $dir,
        #[Option(description: 'A repository (owner/name) to clone beside the catalogue\'s, on its default branch: a package not listed yet')] array $repository = [],
        #[Option(description: 'Where the repositories are (their URL is <from>/<owner>/<name>.git)')] string $from = 'https://github.com',
        #[Option(description: 'Clones run at once')] int $jobs = 8,
    ): int {
        try {
            $cloned = $this->cloner->cloneInto($dir, $repository, $from, $jobs);
        } catch (\InvalidArgumentException|\RuntimeException $e) {
            $io->error($e->getMessage());

            return 1;
        }
        $io->success(\sprintf('%d repositories cloned into %s/%s.', \count($cloned), rtrim($dir, '/'), RepositoryCloner::FOLDER));

        return 0;
    }
}
