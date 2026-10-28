<?php

declare(strict_types=1);

namespace App\GlobalServ\Application\Port\Out;

/**
 * Introduce a temporary pseudo-client, broadcast a NOTICE to all users, then quit it.
 *
 * Returns the recipient count, or null when the network is unavailable.
 */
interface PseudoClientBroadcastPort
{
    public function broadcastFromPseudoClient(string $nick, string $message): ?int;
}
