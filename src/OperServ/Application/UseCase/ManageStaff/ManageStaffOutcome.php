<?php

declare(strict_types=1);

namespace App\OperServ\Application\UseCase\ManageStaff;

enum ManageStaffOutcome
{
    case Added;
    case Deleted;
    case Listed;
    case AlreadyStaff;
    case NotThisRank;
    case NickNotRegistered;
    case NickNotActive;
    case RankUnavailable;
    case InvalidRequest;
    case UnknownAction;
}
