<?php

declare(strict_types=1);

namespace App\ChanServ\Application\Port\In;

enum ChannelAdministrationOutcome
{
    case Updated;

    case Unchanged;

    case NotRegistered;

    case NotInterestGeneral;
    case InterestGeneral;

    case FounderNotRegistered;

    case FounderSuspended;
}
