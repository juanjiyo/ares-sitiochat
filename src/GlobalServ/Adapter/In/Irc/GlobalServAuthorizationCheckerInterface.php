<?php

declare(strict_types=1);

namespace App\GlobalServ\Adapter\In\Irc;

interface GlobalServAuthorizationCheckerInterface
{
    public function isGranted(string $permission, mixed $subject = null): bool;
}
