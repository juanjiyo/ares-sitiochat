<?php

declare(strict_types=1);

namespace App\Irc\Adapter\Network\Event;

/**
 * A global G-Line announced by the IRCd through TKL (burst or live).
 * $expiresAt is a Unix timestamp; 0 means permanent. Removals carry no expiry
 * nor reason.
 */
final readonly class GlineReceivedEvent
{
    public function __construct(
        public bool $added,
        public string $user,
        public string $host,
        public string $setBy,
        public int $expiresAt = 0,
        public string $reason = '',
    ) {}
}
