<?php

declare(strict_types=1);

namespace App\NickServ\Application\UseCase\AutoMode;

interface SetAutoModesHandlerInterface
{
    public function handle(SetAutoModes $command): SetAutoModesResult;
}
