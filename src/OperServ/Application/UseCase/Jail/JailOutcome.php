<?php

declare(strict_types=1);

namespace App\OperServ\Application\UseCase\Jail;

enum JailOutcome
{
    case Jailed;

    case Released;

    case AlreadyJailed;

    case NotJailed;

    case NotOnline;

    case ProtectedRoot;

    case ProtectedIrcOperator;

    case NetworkUnavailable;
}
