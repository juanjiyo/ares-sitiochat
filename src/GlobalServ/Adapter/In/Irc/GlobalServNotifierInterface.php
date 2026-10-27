<?php

declare(strict_types=1);

namespace App\GlobalServ\Adapter\In\Irc;

interface GlobalServNotifierInterface
{
    public function getNick(): string;

    public function getUid(): string;

    /**
     * @param 'NOTICE'|'PRIVMSG' $messageType
     */
    public function sendMessage(string $targetUidOrNick, string $message, string $messageType): void;
}
