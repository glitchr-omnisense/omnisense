<?php

namespace App\Sponsor;

/**
 * What GitHub Sponsors said of the organisation - or why it said nothing.
 *
 *   unconfigured  the site has no token: GitHub was not asked
 *   unreachable   it was asked and did not answer (the reason is kept)
 *   closed        it answered: the organisation has no Sponsors page
 *   open          it answered: the page, its tiers, its goal, its sponsors
 *
 * There is no made-up state: without an answer there are no sponsors, no
 * tiers and no goal to show, and the page says which of the four it is.
 */
final class Sponsors
{
    public const UNCONFIGURED = 'unconfigured';
    public const UNREACHABLE = 'unreachable';
    public const CLOSED = 'closed';
    public const OPEN = 'open';

    /**
     * @param list<array{name: string, description: ?string, monthly: int, oneTime: bool}>                     $tiers
     * @param list<array{login: string, name: ?string, avatar: ?string, url: ?string, tier: ?string}>          $sponsors the public ones
     * @param array{title: ?string, description: ?string, percent: int, target: ?int, kind: ?string}|null      $goal
     */
    public function __construct(
        public readonly string $state,
        public readonly string $login,
        public readonly ?string $url = null,
        public readonly ?string $description = null,
        public readonly array $tiers = [],
        public readonly array $sponsors = [],
        /** Every active sponsorship, the private ones included. */
        public readonly int $total = 0,
        public readonly ?array $goal = null,
        public readonly ?string $reason = null,
        public readonly ?\DateTimeImmutable $at = null,
    ) {
    }

    public function isOpen(): bool
    {
        return self::OPEN === $this->state;
    }

    /** Sponsors who chose not to be named: counted, never listed. */
    public function getPrivateCount(): int
    {
        return max(0, $this->total - \count($this->sponsors));
    }
}
