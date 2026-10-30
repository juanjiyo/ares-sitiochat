<?php

declare(strict_types=1);

namespace App\OperServ\Application\UseCase\ManageEline;

enum ElineAction
{
    case Add;

    case Delete;

    case List;

    case Count;
}
