<?php

declare(strict_types=1);

namespace App\GlobalServ\Application\UseCase\SendChannel;

/** Typed intention to send a GlobalServ message to every member of a channel. */
final readonly class SendChannelMessage
{
    public function __construct(
        public string $actorNickname,
        public string $channelName,
        public string $message,
    ) {}
}
