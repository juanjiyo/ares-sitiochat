<?php

declare(strict_types=1);

namespace App\GlobalServ\Application\Port\Out;

/**
 * Network identity of the GlobalServ pseudo-client on the active connection.
 *
 * Returns null before the service has been introduced to the network.
 */
interface GlobalServNetworkIdentity
{
    public function serviceUid(): ?string;
}
