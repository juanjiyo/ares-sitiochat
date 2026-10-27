<?php

declare(strict_types=1);

namespace App\GlobalServ\Adapter\Out\Irc;

use App\GlobalServ\Application\Port\Out\GlobalServNetworkIdentity;
use App\GlobalServ\Application\Port\Out\RankFilteredBroadcastPort;
use App\Irc\Application\Port\In\ActiveConnectionHolderInterface;
use App\Irc\Application\Port\In\SendNoticePort;

/** Sends a GlobalServ NOTICE to an already filtered list of UIDs. */
final readonly class ActiveConnectionRankFilteredBroadcast implements RankFilteredBroadcastPort
{
    public function __construct(
        private ActiveConnectionHolderInterface $connection,
        private GlobalServNetworkIdentity $identity,
        private SendNoticePort $messages,
    ) {}

    /**
     * @param list<string> $targetUids
     */
    public function broadcastToUids(array $targetUids, string $message): ?int
    {
        if (!$this->connection->isConnected()) {
            return null;
        }

        $serviceUid = $this->identity->serviceUid();
        if (null === $serviceUid) {
            return null;
        }

        $count = 0;
        foreach ($targetUids as $targetUid) {
            $this->messages->sendNotice($serviceUid, $targetUid, $message);
            ++$count;
        }

        return $count;
    }
}
