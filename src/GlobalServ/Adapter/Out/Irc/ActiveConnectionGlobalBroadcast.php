<?php

declare(strict_types=1);

namespace App\GlobalServ\Adapter\Out\Irc;

use App\GlobalServ\Application\Port\Out\GlobalBroadcastPort;
use App\Irc\Application\Port\In\ActiveConnectionHolderInterface;
use App\Irc\Application\Port\In\NetworkUserLookupPort;
use App\Irc\Application\Port\In\SendNoticePort;

/**
 * Bridges GlobalServ's semantic broadcast to the active S2S connection: one
 * NOTICE from the service UID to every connected user.
 *
 * Controlled duplicate of OperServ's global transport: protocol rules forbid
 * sharing behavioral bases between services, so this adapter is owned entirely
 * by GlobalServ.
 */
final readonly class ActiveConnectionGlobalBroadcast implements GlobalBroadcastPort
{
    public function __construct(
        private ActiveConnectionHolderInterface $connection,
        private NetworkUserLookupPort $users,
        private SendNoticePort $messages,
    ) {}

    public function broadcastNotice(string $serviceUid, string $message): ?int
    {
        if (!$this->connection->isConnected()) {
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
