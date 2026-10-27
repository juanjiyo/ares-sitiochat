<?php

declare(strict_types=1);

namespace App\GlobalServ\Application\UseCase\SendGlobal;

/** Typed intention to broadcast a message from the GlobalServ pseudo-client. */
final readonly class SendGlobalMessage
{
    public function __construct(
        public string $actorNickname,
        public string $message,
    ) {}
}
