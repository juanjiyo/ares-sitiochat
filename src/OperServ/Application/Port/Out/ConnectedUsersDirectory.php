<?php

declare(strict_types=1);

namespace App\OperServ\Application\Port\Out;

/** Consumer-owned snapshot of every user currently connected to the network. */
interface ConnectedUsersDirectory
{
    /** @return list<NetworkUser> */
    public function listConnected(): array;
}
