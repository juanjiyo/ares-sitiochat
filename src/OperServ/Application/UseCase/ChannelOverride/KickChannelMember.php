<?php

declare(strict_types=1);

namespace App\OperServ\Application\UseCase\ChannelOverride;

use DateTimeImmutable;

/** Typed intention to remove a member from a channel through OperServ. */
final readonly class KickChannelMember
{
    public function __construct(
        public string $actorNickname,
        public string $serviceNickname,
        public string $channelName,
        public string $targetNickname,
        public string $reason,
        public DateTimeImmutable $occurredAt,
    ) {}
}
