<?php

declare(strict_types=1);

namespace App\OperServ\Application\Port\Out;

/** A global spamfilter of the IRCd ($targets and $action as IRCd letters). */
final readonly class SpamfilterEntry
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
}
