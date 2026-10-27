<?php

declare(strict_types=1);

namespace App\ChanServ\Application\UseCase\KickUser;

final readonly class KickUser
{
    public function __construct(
        public string $channelName,
        public string $targetNickname,
        public string $actorNickname,
        public string $reason,
    ) {}
}
