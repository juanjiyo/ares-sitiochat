<?php

declare(strict_types=1);

namespace App\OperServ\Application\Security;

final readonly class OperServIrcopPermission implements PermissionProviderInterface
{
    public function getServiceName(): string
    {
        return 'OperServ';
    }

    public function getPermissions(): array
    {
        return [
            OperServPermission::KILL,
            OperServPermission::GLINE,
            OperServPermission::GEOIP,
            OperServPermission::RAW,
            OperServPermission::MOTD,
            OperServPermission::KICK,
            OperServPermission::OP,
            OperServPermission::MODE,
            OperServPermission::INVITE,
            OperServPermission::CLONES,
            OperServPermission::CONEXIONES,
            OperServPermission::UMODE,
            OperServPermission::JAIL,
            OperServPermission::ELINE,
        ];
    }
}
