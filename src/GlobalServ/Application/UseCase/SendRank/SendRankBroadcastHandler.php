<?php

declare(strict_types=1);

namespace App\GlobalServ\Application\UseCase\SendRank;

use App\GlobalServ\Application\Model\BroadcastResult;
use App\GlobalServ\Application\Port\Out\OperatorRankLookupPort;
use App\GlobalServ\Application\Port\Out\RankFilteredBroadcastPort;
use App\Irc\Application\Port\In\NetworkUserLookupPort;

/** Broadcasts a message to every connected user with at least the requested staff rank. */
final readonly class SendRankBroadcastHandler
{
    public function __construct(
        private NetworkUserLookupPort $users,
        private OperatorRankLookupPort $rankLookup,
        private RankFilteredBroadcastPort $broadcast,
    ) {}

    public function handle(SendRankBroadcast $command): BroadcastResult
    {
        $targetUids = [];

        foreach ($this->users->listConnectedUids() as $uid) {
            $hasRank = $this->rankLookup->hasAtLeastRank($uid, $command->rank);
            if (null === $hasRank) {
                return BroadcastResult::rankUnsupported();
            }

            if ($hasRank) {
                $targetUids[] = $uid;
            }
        }

        $count = $this->broadcast->broadcastToUids($targetUids, $command->message);

        return null === $count
            ? BroadcastResult::networkUnavailable()
            : BroadcastResult::sent($count);
    }
}
