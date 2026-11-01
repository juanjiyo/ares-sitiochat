<?php

declare(strict_types=1);

namespace App\OperServ\Application\UseCase\SetPassProtect;

enum SetPassProtectOutcome
{
    case Set;
    case InvalidValue;
    case Rejected;
}
