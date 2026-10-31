<?php

declare(strict_types=1);

namespace App\ChanServ\Application\Port\In;

enum OfficialRegistrationOutcome
{
    case Registered;

    case AlreadyRegistered;

    case PendingDeletion;

    case Failed;

    case FounderNotRegistered;

    case FounderSuspended;
}
