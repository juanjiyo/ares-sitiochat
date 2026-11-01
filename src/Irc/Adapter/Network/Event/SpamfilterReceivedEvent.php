<?php

declare(strict_types=1);

namespace App\Irc\Adapter\Network\Event;

/**
 * A global spamfilter announced by the IRCd through TKL F (burst or live).
 * $targets and $action are the IRCd letters (e.g. "cpn", "g"); $reason has
 * its "_" decoded back to spaces. On removal $setBy is whoever removed it.
 */
final readonly class SpamfilterReceivedEvent
{
    public function __construct(
        public bool $added,
        public string $targets,
        public string $action,
        public string $setBy,
        public string $matchType,
        public string $match,
        public int $banDuration,
        public string $reason,
    ) {}
}
