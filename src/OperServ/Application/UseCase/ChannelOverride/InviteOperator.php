<?php

declare(strict_types=1);

namespace App\OperServ\Application\UseCase\ChannelOverride;

use DateTimeImmutable;

/** Typed intention to invite the requesting IRC operator into a channel through OperServ. */
final readonly class InviteOperator
{
    public function __construct(
        public string $actorNickname,
        public string $actorUid,
        public string $channelName,
        public DateTimeImmutable $occurredAt,
    ) {}
}
