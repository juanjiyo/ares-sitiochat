<?php

declare(strict_types=1);

namespace App\GlobalServ\Application\UseCase\SendRank;

use App\GlobalServ\Domain\ValueObject\GlobalServRank;

/** Typed intention to broadcast a message to every user with at least a staff rank. */
final readonly class SendRankBroadcast
{
    public function __construct(
        public string $actorNickname,
        public GlobalServRank $rank,
        public string $message,
    ) {}
}
