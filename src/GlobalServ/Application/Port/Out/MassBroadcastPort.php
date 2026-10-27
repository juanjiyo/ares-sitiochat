<?php

declare(strict_types=1);

namespace App\GlobalServ\Application\Port\Out;

/**
 * Broadcast a NOTICE from the GlobalServ pseudo-client to every connected user.
 *
 * Returns the recipient count, or null when the network is unavailable.
 */
interface MassBroadcastPort
{
    public function broadcastToAll(string $message): ?int;
}
