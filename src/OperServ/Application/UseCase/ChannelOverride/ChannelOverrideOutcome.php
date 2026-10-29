<?php

declare(strict_types=1);

namespace App\OperServ\Application\UseCase\ChannelOverride;

/** Semantic outcomes shared by the IRCop channel override use cases. */
enum ChannelOverrideOutcome: string
{
    case Applied = 'applied';
    case InvalidChannel = 'invalid_channel';
    case ChannelNotFound = 'channel_not_found';
    case NotOnline = 'not_online';
    case NotOnChannel = 'not_on_channel';
    case AlreadyOnChannel = 'already_on_channel';
    case ProtectedRoot = 'protected_root';
    case ProtectedIrcOperator = 'protected_irc_operator';
    case InvalidModes = 'invalid_modes';
    case NetworkUnavailable = 'network_unavailable';
}
