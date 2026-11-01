<?php

declare(strict_types=1);

namespace App\OperServ\Application\UseCase\ManageSpamfilter;

enum SpamfilterAction
{
    case Add;
    case Delete;
    case List;
    case Unknown;
}
