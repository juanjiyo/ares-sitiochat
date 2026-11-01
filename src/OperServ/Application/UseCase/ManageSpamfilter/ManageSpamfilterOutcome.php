<?php

declare(strict_types=1);

namespace App\OperServ\Application\UseCase\ManageSpamfilter;

enum ManageSpamfilterOutcome
{
    case Added;
    case AlreadyExists;
    case Deleted;
    case NotFound;
    case Listed;
    case ListEmpty;
    case InvalidRequest;
    case InvalidTargets;
    case InvalidAction;
    case InvalidRegex;
    case InvalidDuration;
    case Unavailable;
    case UnknownAction;
}
