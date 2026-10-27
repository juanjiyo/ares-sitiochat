<?php

declare(strict_types=1);

namespace App\ChanServ\Application\UseCase\KickUser;

enum KickUserOutcome
{
    case Kicked;

    case ChannelNotRegistered;

    case ChannelNotOnNetwork;

    case TargetNotConnected;
}
