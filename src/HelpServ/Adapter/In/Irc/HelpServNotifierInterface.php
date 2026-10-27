<?php

declare(strict_types=1);

namespace App\HelpServ\Adapter\In\Irc;

/**
 * Port implemented by the HeLPServ bot for sending replies to users.
 */
interface HelpServNotifierInterface
{
    public function sendNotice(string $targetUidOrNick, string $message): void;

    /**
     * @param 'NOTICE'|'PRIVMSG' $messageType
     */
    public function sendMessage(string $targetUidOrNick, string $message, string $messageType): void;

    public function getNick(): string;

    public function getServiceKey(): string;
}
