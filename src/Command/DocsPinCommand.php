<?php

namespace App\Command;

use App\Content\DocsPinner;
use Symfony\Component\Console\Attribute\Argument;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Attribute\Option;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * One manual's series moved to a commit of its branch, in
 * config/documentation.yaml: what the website's workflow runs when a
 * documentation package dispatches `documentation-published`. Exit codes:
 * 0 moved or already there, 1 refused (not on the branch, fetch failed),
 * 2 an unknown manual or series (the workflow then builds the whole site).
 */
#[AsCommand(name: 'app:docs:pin', description: 'Move one manual\'s series in config/documentation.yaml to a commit of the branch it follows (its head by default), checked on that branch')]
final class DocsPinCommand
{
    public const UNKNOWN = 2;

    public function __construct(private readonly DocsPinner $pinner)
    {
    }

    public function __invoke(
        SymfonyStyle $io,
        #[Argument(description: 'The manual\'s key (config/documentation.yaml)')] string $key,
        #[Argument(description: 'A full commit SHA on the series\' branch; its head when omitted')] ?string $commit = null,
        #[Option(description: 'The series (the manual\'s default when omitted)')] ?string $series = null,
    ): int {
        try {
            $pin = $this->pinner->pin($key, '' === $commit ? null : $commit, $series);
        } catch (\InvalidArgumentException $e) {
            $io->error($e->getMessage());

            return self::UNKNOWN;
        } catch (\RuntimeException $e) {
            $io->error($e->getMessage());

            return 1;
        }

        $io->success($pin['moved']
            ? \sprintf('%s %s: %s -> %s', $pin['key'], $pin['series'], $pin['from'], $pin['to'])
            : \sprintf('%s %s: already at %s', $pin['key'], $pin['series'], $pin['to']));

        return 0;
    }
}
