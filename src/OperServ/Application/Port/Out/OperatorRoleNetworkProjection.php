<?php

declare(strict_types=1);

namespace App\OperServ\Application\Port\Out;

/** Applies a role's changed settings to the operators that hold it. */
interface OperatorRoleNetworkProjection
{
    public function refreshVhost(int $roleId, ?string $pattern): void;

    public function refreshOperclass(int $roleId, ?string $operclass): void;
}
