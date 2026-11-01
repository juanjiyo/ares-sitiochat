<?php

declare(strict_types=1);

namespace App\OperServ\Adapter\Out\Irc;

use App\Irc\Application\Port\In\PasswordFloodSettingPort;
use App\OperServ\Application\Port\Out\PasswordFloodSettings;

final readonly class IrcPasswordFloodSettings implements PasswordFloodSettings
{
    public function __construct(
        private PasswordFloodSettingPort $setting,
    ) {}

    public function set(int $attempts, int $seconds): bool
    {
        return $this->setting->set($attempts, $seconds);
    }
}
