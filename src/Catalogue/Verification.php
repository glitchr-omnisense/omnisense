<?php

namespace App\Catalogue;

/**
 * Whether a package was tried against the real service it talks to - as its
 * own README says, never as this site supposes:
 *
 *   verified    the README says it was ("Verified against the real service on ...")
 *   partial     some of it was, some of it was not: it says both, or it says
 *               so of one part (a reservation under a section of its own)
 *   unverified  it says it was not ("Unverified. Written from the published documentation ...")
 *   unstated    it says nothing either way
 */
enum Verification: string
{
    case Verified = 'verified';
    case Partial = 'partial';
    case Unverified = 'unverified';
    case Unstated = 'unstated';

    /**
     * Reads a README. Table rows are left out: a family's README lists its
     * providers there, each with its own state, which is not the family's.
     *
     * @return array{0: self, 1: ?string} the state, and the sentence it was read in
     */
    public static function read(string $readme): array
    {
        $negative = null;
        $positive = null;
        // A reservation written under a section of its own is about that part of the package, not all of it.
        $inSection = false;
        $partial = false;

        $text = preg_replace('/^```.*?^```/ms', '', $readme) ?? $readme;
        foreach (preg_split('/\R{2,}/', $text) ?: [] as $block) {
            if (preg_match('/^\s*\|/', $block)) {
                continue;
            }
            $heading = 1 === preg_match('/^\s*#{2,6}\s/', $block);
            // A quote's marks go, a paragraph's lines are joined, a list keeps its items apart.
            $block = preg_replace('/^[ \t]*>[ \t]?/m', '', $block) ?? $block;
            $block = preg_replace('/\R(?![ \t]*(?:[-*+]|\d+\.)[ \t])/', ' ', $block) ?? $block;
            foreach (preg_split('/(?<=[.!?])\s+(?=[A-Z*\[`(])|\R/', $block) ?: [] as $sentence) {
                $plain = trim((string) preg_replace('/\s+/', ' ', $sentence));
                if ('' === $plain) {
                    continue;
                }
                if (preg_match('/\b(unverified|not (?:been )?verified|never (?:been )?verified|non vérifié)/i', $plain)) {
                    if (null === $negative) {
                        $negative = $plain;
                        // "## Not verified against the real service" speaks of the whole package.
                        $partial = $inSection && !$heading;
                    }
                } elseif (preg_match('/\b(verified (?:against|on \d|call by call|live|in real|with a real)|what was verified|vérifié en réel)/i', $plain)) {
                    $positive ??= $plain;
                    // "...tokens and webhooks against the documentation only."
                    $partial = $partial || 1 === preg_match('/\b(documentation only|only against|not yet|except)\b/i', $plain);
                }
            }
            $inSection = $inSection || $heading;
        }

        $clean = static fn (string $s): string => mb_substr(trim((string) preg_replace('/\s+/', ' ', strtr((string) preg_replace('/\[([^\]]*)\]\([^)]*\)/', '$1', $s), ['**' => '', '`' => '', '#' => '']))), 0, 320);

        return match (true) {
            null !== $negative && null !== $positive => [self::Partial, $clean($positive).' — '.$clean($negative)],
            null !== $negative => [$partial ? self::Partial : self::Unverified, $clean($negative)],
            null !== $positive => [$partial ? self::Partial : self::Verified, $clean($positive)],
            default => [self::Unstated, null],
        };
    }
}
