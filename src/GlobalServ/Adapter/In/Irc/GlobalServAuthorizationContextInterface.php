<?php

declare(strict_types=1);

namespace App\GlobalServ\Adapter\In\Irc;

interface GlobalServAuthorizationContextInterface
{
    public function setCurrentUser(string $uid, bool $isIdentified, bool $isOper): void;

    public function clear(): void;
}
