<?php

declare(strict_types=1);

namespace App\OperServ\Application\UseCase\ChannelOverride;

use DateTimeImmutable;

/** Typed intention to grant (OP) or remove (DEOP) channel operator status through OperServ. */
final readonly class SetChannelOperator
{
    public function __construct(
        public string $actorNickname,
        public string $channelName,
        public string $targetNickname,
        public bool $grant,
        public DateTimeImmutable $occurredAt,
    ) {}
}
