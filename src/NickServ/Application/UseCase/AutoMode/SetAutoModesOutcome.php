<?php

declare(strict_types=1);

namespace App\NickServ\Application\UseCase\AutoMode;

enum SetAutoModesOutcome
{
    case Set;
    case Cleared;
    case InvalidModes;
    case NotRegistered;
    case TargetIsRoot;
    case TargetIsIrcop;
    case TargetIsService;
}
