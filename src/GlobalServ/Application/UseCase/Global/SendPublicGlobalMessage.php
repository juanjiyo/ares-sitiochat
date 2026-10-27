<?php

declare(strict_types=1);

namespace App\GlobalServ\Application\UseCase\Global;

use DateTimeImmutable;

/** Typed intention to broadcast a public announcement to the whole network. */
final readonly class SendPublicGlobalMessage
{
    public function __construct(
        public string $actorNickname,
        public string $message,
        public DateTimeImmutable $occurredAt,
    ) {}
}
