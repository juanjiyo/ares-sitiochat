<?php

declare(strict_types=1);

namespace App\GlobalServ\Application\UseCase\SendNick;

/** Typed intention to introduce a temporary pseudo-client and broadcast a message. */
final readonly class SendNickBroadcast
{
    public function __construct(
        public string $actorNickname,
        public string $nick,
        public string $message,
    ) {}
}
