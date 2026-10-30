<?php

declare(strict_types=1);

namespace App\OperServ\Application\UseCase\ManageEline;

enum ManageElineOutcome
{
    case Added;

    case AlreadyExists;

    case InvalidMask;

    case InvalidExpiry;

    case Deleted;

    case NotFound;

    case Listed;

    case Counted;

    case Cleared;

    case NetworkUnavailable;
}
