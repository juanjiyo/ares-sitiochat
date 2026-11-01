<?php

declare(strict_types=1);

namespace App\AntiSpam\Adapter\In\Irc;

interface AntiSpamAuthorizationContextInterface
{
    public function setCurrentUser(string $uid, bool $isIdentified, bool $isOper): void;

    public function clear(): void;
}
