<?php

declare(strict_types=1);

namespace App\OperServ\Application\UseCase\Clones;

enum BanCloneHostOutcome
{
    case Banned;
    case NotOnline;
    case LoopbackHost;
    case ProtectedRoot;
    case ProtectedIrcOperator;
    /** A protected G-Line subject matches the mask (GLINE ADD protection). */
    case ProtectedSubject;
    case AlreadyBanned;
    case UnsafeHost;
    case LimitReached;
    case Failed;
}
