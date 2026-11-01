<?php

declare(strict_types=1);

namespace App\AntiSpam\Adapter\In\Irc;

interface AntiSpamAuthorizationCheckerInterface
{
    public function isGranted(string $permission, mixed $subject = null): bool;
}
