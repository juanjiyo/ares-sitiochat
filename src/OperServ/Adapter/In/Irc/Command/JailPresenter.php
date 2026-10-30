<?php

declare(strict_types=1);

namespace App\OperServ\Adapter\In\Irc\Command;

use App\OperServ\Adapter\In\Irc\OperServContext;
use App\OperServ\Application\UseCase\Jail\JailOutcome;
use App\OperServ\Application\UseCase\Jail\JailResult;

/** Maps JAIL and UNJAIL outcomes to IRC replies. */
final class JailPresenter
{
    private function __construct() {}

    public static function present(OperServContext $context, JailResult $result): void
    {
        $params = ['nickname' => $result->targetNickname, 'channel' => $result->jailChannel];

        match ($result->outcome) {
            JailOutcome::Jailed => $context->reply('jail.done', $params),
            JailOutcome::Released => $context->reply('unjail.done', $params),
            JailOutcome::AlreadyJailed => $context->reply('jail.already', $params),
            JailOutcome::NotJailed => $context->reply('unjail.not_jailed', $params),
            JailOutcome::NotOnline => $context->reply('channel.user_not_online', $params),
            JailOutcome::ProtectedRoot => $context->reply('channel.protected_root', $params),
            JailOutcome::ProtectedIrcOperator => $context->reply('channel.protected_ircop', $params),
            JailOutcome::NetworkUnavailable => null,
        };
    }
}
