<?php

declare(strict_types=1);

namespace App\OperServ\Application\Port\Out;

/** Applies user modes on the network after the application has authorised them. */
interface UserModeNetworkActions
{
    /** Returns false when no active network protocol can perform the action. */
    public function apply(string $targetUid, string $modes): bool;
}
