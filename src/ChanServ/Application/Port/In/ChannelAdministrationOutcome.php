<?php

declare(strict_types=1);

namespace App\ChanServ\Application\Port\In;

enum ChannelAdministrationOutcome
{
    case Updated;

    case Unchanged;

    case NotRegistered;

    case NotInterestGeneral;

    case FounderNotRegistered;

    case FounderSuspended;
}
