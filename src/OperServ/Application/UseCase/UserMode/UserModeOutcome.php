<?php

declare(strict_types=1);

namespace App\OperServ\Application\UseCase\UserMode;

enum UserModeOutcome
{
    case Applied;

    case InvalidModes;

    case ForbiddenModes;

    case AboveRank;

    case NotOnline;

    case ProtectedRoot;

    case ProtectedIrcOperator;

    case NetworkUnavailable;
}
