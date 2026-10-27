<?php

declare(strict_types=1);

namespace App\GlobalServ\Application\Model;

enum BroadcastOutcome
{
    case Sent;

    case NetworkUnavailable;

    case ChannelNotFound;

    case JoinUnsupported;

    case RankUnsupported;
}
