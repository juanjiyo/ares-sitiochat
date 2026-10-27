<?php

declare(strict_types=1);

namespace App\GlobalServ\Application\UseCase\MassJoin;

/** Typed intention to SVSJOIN every user into a channel and broadcast the reason. */
final readonly class MassJoinChannel
{
    public function __construct(
        public string $actorNickname,
        public string $channelName,
        public string $reason,
    ) {}
}
