<?php

declare(strict_types=1);

namespace App\GlobalServ\Application\UseCase\MassJoin;

use App\GlobalServ\Application\Model\BroadcastResult;
use App\GlobalServ\Application\Port\Out\MassBroadcastPort;
use App\GlobalServ\Application\Port\Out\MassChannelJoinPort;

/** SVSJOINs every connected user into a channel and broadcasts the reason. */
final readonly class MassJoinChannelHandler
{
    public function __construct(
        private MassChannelJoinPort $join,
        private MassBroadcastPort $broadcast,
    ) {}

    public function handle(MassJoinChannel $command): BroadcastResult
    {
        $joinResult = $this->join->joinAllUsersToChannel($command->channelName, $command->reason);
        if (!$joinResult->supported) {
            return BroadcastResult::joinUnsupported();
        }

        $count = $this->broadcast->broadcastToAll($command->reason);

        return null === $count
            ? BroadcastResult::networkUnavailable()
            : BroadcastResult::sent($count);
    }
}
