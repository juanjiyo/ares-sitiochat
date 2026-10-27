<?php

declare(strict_types=1);

namespace App\GlobalServ\Application\Security;

use App\OperServ\Application\Security\PermissionProviderInterface;

/**
 * Registers the GlobalServ IRCop permissions in the shared operclass catalog so
 * they can be assigned with ROLE PERMS and listed alongside the other services.
 */
final readonly class GlobalServIrcopPermission implements PermissionProviderInterface
{
    public function getServiceName(): string
    {
        return 'GlobalServ';
    }

    public function getPermissions(): array
    {
        return GlobalServPermission::allIrcop();
    }
}
