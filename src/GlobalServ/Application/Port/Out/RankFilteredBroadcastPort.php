<?php

declare(strict_types=1);

namespace App\GlobalServ\Application\Port\Out;

/**
 * Broadcast a NOTICE from the GlobalServ pseudo-client to a filtered UID list.
 *
 * Returns the recipient count, or null when the network is unavailable.
 */
interface RankFilteredBroadcastPort
{
    /**
     * @param list<string> $targetUids
     */
    public function broadcastToUids(array $targetUids, string $message): ?int;
}
