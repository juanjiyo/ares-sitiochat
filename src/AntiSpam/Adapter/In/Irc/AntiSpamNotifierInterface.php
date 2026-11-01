<?php

declare(strict_types=1);

namespace App\AntiSpam\Adapter\In\Irc;

/**
 * Port implemented by the AntiSpam bot for sending replies to users.
 */
interface AntiSpamNotifierInterface
{
    public function sendNotice(string $targetUidOrNick, string $message): void;

    /**
     * @param 'NOTICE'|'PRIVMSG' $messageType
     */
    public function sendMessage(string $targetUidOrNick, string $message, string $messageType): void;

    public function getNick(): string;

    public function getServiceKey(): string;
}
