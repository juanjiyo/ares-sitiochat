<?php

declare(strict_types=1);

namespace App\OperServ\Application\UseCase\ManageStaff;

enum StaffAction
{
    case Add;
    case Delete;
    case List;
    case Unknown;
}
