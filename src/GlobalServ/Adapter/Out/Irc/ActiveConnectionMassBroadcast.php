<?php

declare(strict_types=1);

namespace App\GlobalServ\Adapter\Out\Irc;

use App\GlobalServ\Application\Port\Out\GlobalServNetworkIdentity;
use App\GlobalServ\Application\Port\Out\MassBroadcastPort;
use App\Irc\Application\Port\In\ActiveConnectionHolderInterface;
use App\Irc\Application\Port\In\NetworkUserLookupPort;
use App\Irc\Application\Port\In\SendNoticePort;

/**
 * Broadcasts a GlobalServ NOTICE to every connected user from the service UID.
 *
 * Kept inside GlobalServ on purpose: protocol rules forbid sharing behavioural
 * bases between bounded contexts, even when the wire shape looks identical.
 */
final readonly class ActiveConnectionMassBroadcast implements MassBroadcastPort
{
    public function __construct(
        private ActiveConnectionHolderInterface $connection,
        private GlobalServNetworkIdentity $identity,
        private NetworkUserLookupPort $users,
        private SendNoticePort $messages,
    ) {}

    public function broadcastToAll(string $message): ?int
    {
        if (!$this->connection->isConnected()) {
            return null;
        }

        $serviceUid = $this->identity->serviceUid();
        if (null === $serviceUid) {
            return null;
        }

        $count = 0;
        foreach ($this->users->listConnectedUids() as $targetUid) {
            $this->messages->sendNotice($serviceUid, $targetUid, $message);
            ++$count;
        }

        return $count;
    }
}
