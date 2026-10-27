<?php

declare(strict_types=1);

namespace App\GlobalServ\Application\UseCase\Global;

/** Contract the GlobalServ GLOBAL command depends on. */
interface SendPublicGlobalMessageHandlerInterface
{
    public function handle(SendPublicGlobalMessage $command): SendPublicGlobalMessageResult;
}
