<?php

declare(strict_types=1);

namespace App\AntiSpam\Application\Security;

use App\OperServ\Application\Security\PermissionProviderInterface;

/**
 * Registers the AntiSpam IRCop permissions in the shared operclass catalog so
 * they can be assigned with ROLE PERMS and listed alongside the other services.
 */
final readonly class AntiSpamIrcopPermission implements PermissionProviderInterface
{
    public function getServiceName(): string
    {
        return 'AntiSpam';
    }

    public function getPermissions(): array
    {
        return AntiSpamPermission::allIrcop();
    }
}
