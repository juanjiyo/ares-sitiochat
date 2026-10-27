<?php

declare(strict_types=1);

namespace App\GlobalServ\Adapter\Out\Irc;

use App\GlobalServ\Application\Model\MassChannelJoinResult;
use App\GlobalServ\Application\Port\Out\MassChannelJoinPort;

/**
 * Reports JOIN as unsupported when the active protocol adapter exposes no
 * SVSJOIN-equivalent capability.
 *
 * ProtocolServiceActionsInterface currently has no force-join operation, and
 * GlobalServ must not emit raw IRC lines to work around it.
 */
final readonly class UnsupportedMassChannelJoin implements MassChannelJoinPort
{
    public function joinAllUsersToChannel(string $channelName, string $reason): MassChannelJoinResult
    {
        return MassChannelJoinResult::unsupported();
    }
}
