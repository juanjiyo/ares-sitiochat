<?php

declare(strict_types=1);

namespace App\OperServ\Adapter\In\Irc\Command;

use App\OperServ\Adapter\In\Irc\OperServContext;
use App\OperServ\Application\UseCase\ChannelOverride\ChannelOverrideOutcome;
use App\OperServ\Application\UseCase\ChannelOverride\ChannelOverrideResult;

/** Maps channel override outcomes shared by KICK, OP, DEOP, MODE, LIMPIA and INVITE to IRC replies. */
final class ChannelOverridePresenter
{
    private function __construct() {}

    /**
     * @param string $doneKey     translation key of the command success reply
     * @param string $detailParam placeholder that receives the result detail in the success reply
     */
    public static function present(OperServContext $context, ChannelOverrideResult $result, string $doneKey, string $detailParam = 'detail'): void
    {
        $channel = ['channel' => $result->channelName];
        $nickname = ['nickname' => $result->targetNickname];

        match ($result->outcome) {
            ChannelOverrideOutcome::Applied => $context->reply($doneKey, [...$channel, ...$nickname, $detailParam => $result->detail]),
            ChannelOverrideOutcome::InvalidChannel => $context->reply('channel.invalid_name', $channel),
            ChannelOverrideOutcome::ChannelNotFound => $context->reply('channel.not_found', $channel),
            ChannelOverrideOutcome::NotOnline => $context->reply('channel.user_not_online', $nickname),
            ChannelOverrideOutcome::NotOnChannel => $context->reply('channel.user_not_on_channel', [...$channel, ...$nickname]),
            ChannelOverrideOutcome::AlreadyOnChannel => $context->reply('invite.already_on_channel', $channel),
            ChannelOverrideOutcome::ProtectedRoot => $context->reply('channel.protected_root', $nickname),
            ChannelOverrideOutcome::ProtectedIrcOperator => $context->reply('channel.protected_ircop', $nickname),
            ChannelOverrideOutcome::InvalidModes => $context->reply('mode.invalid_modes', ['modes' => $result->detail]),
            ChannelOverrideOutcome::NetworkUnavailable => null,
        };
    }
}
