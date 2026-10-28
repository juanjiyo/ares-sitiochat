<?php

declare(strict_types=1);

namespace App\GlobalServ\Application\Port\Out;

/**
 * Network capability: send a notice from a service UID to every connected user.
 *
 * Returns null when the network is unavailable, otherwise the recipient count.
 */
interface GlobalBroadcastPort
{
    public function broadcastNotice(string $serviceUid, string $message): ?int;
}
