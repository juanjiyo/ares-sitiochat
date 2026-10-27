<?php

declare(strict_types=1);

namespace App\GlobalServ\Application\Port\Out;

use App\GlobalServ\Application\Model\MassChannelJoinResult;

/**
 * Force-join every connected user into a channel (SVSJOIN semantics).
 *
 * The implementation is protocol-specific. When the active protocol adapter does
 * not expose this capability, the result reports unsupported instead of emitting
 * raw IRC lines.
 */
interface MassChannelJoinPort
{
    public function joinAllUsersToChannel(string $channelName, string $reason): MassChannelJoinResult;
}
