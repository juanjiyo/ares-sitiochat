<?php

declare(strict_types=1);

namespace App\Irc\Application\Port\In;

/**
 * A global spamfilter of the IRCd. $targets and $action are the IRCd letters
 * (e.g. "cpn", "g"); $matchType is "simple" or "regex"; $banDuration is in
 * seconds and only matters for ban actions.
 */
final readonly class Spamfilter
{
    public function __construct(
        public string $targets,
        public string $action,
        public string $matchType,
        public string $match,
        public int $banDuration,
        public string $reason,
        public string $setBy,
    ) {}

    /** The IRCd identifies a spamfilter by its targets, action and match string. */
    public function key(): string
    {
        return self::keyOf($this->targets, $this->action, $this->match);
    }

    public static function keyOf(string $targets, string $action, string $match): string
    {
        return $targets . ' ' . $action . ' ' . $match;
    }
}
