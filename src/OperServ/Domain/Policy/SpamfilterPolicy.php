<?php

declare(strict_types=1);

namespace App\OperServ\Domain\Policy;

use function array_flip;
use function array_key_exists;
use function array_keys;
use function str_contains;
use function strlen;
use function strtolower;
use function strtoupper;

/**
 * Spamfilter vocabulary of UnrealIRCd 6: where a pattern is looked for
 * (targets) and what happens on a match (action), with their IRCd letters.
 */
final class SpamfilterPolicy
{
    /** c channel, p private, n private notice, N channel notice, P part, q quit, d dcc, a away, t topic. */
    public const string TARGETS = 'cpnNPqdat';

    /** "ALL": every text a user can send (as in the original nospam module). */
    public const string ALL_TARGETS = 'cpnNPqat';

    /** @var array<string, string> name => IRCd letter */
    private const array ACTIONS = [
        'warn' => 'w',
        'block' => 'b',
        'kill' => 'K',
        'tempshun' => 'S',
        'shun' => 's',
        'kline' => 'k',
        'gline' => 'g',
        'zline' => 'z',
        'gzline' => 'Z',
        'dccblock' => 'd',
        'viruschan' => 'v',
    ];

    /** Letters of the actions that place a timed ban (the ban duration applies). */
    private const string TIMED_BAN_ACTIONS = 'skgzZ';

    private function __construct() {}

    /** @return list<string> */
    public static function actionNames(): array
    {
        return array_keys(self::ACTIONS);
    }

    public static function actionLetter(string $name): ?string
    {
        return self::ACTIONS[strtolower($name)] ?? null;
    }

    /** Readable name of an IRCd action letter; unknown letters are returned as-is. */
    public static function actionName(string $letter): string
    {
        $names = array_flip(self::ACTIONS);

        return array_key_exists($letter, $names) ? $names[$letter] : $letter;
    }

    public static function isTimedBan(string $letter): bool
    {
        return '' !== $letter && str_contains(self::TIMED_BAN_ACTIONS, $letter);
    }

    /** Target letters in the IRCd order without repeats; null when empty or with an unknown letter. */
    public static function targets(string $input): ?string
    {
        if ('ALL' === strtoupper($input)) {
            return self::ALL_TARGETS;
        }
        if ('' === $input) {
            return null;
        }
        for ($i = 0, $length = strlen($input); $i < $length; ++$i) {
            if (!str_contains(self::TARGETS, $input[$i])) {
                return null;
            }
        }

        $targets = '';
        for ($i = 0, $length = strlen(self::TARGETS); $i < $length; ++$i) {
            if (str_contains($input, self::TARGETS[$i])) {
                $targets .= self::TARGETS[$i];
            }
        }

        return $targets;
    }
}
