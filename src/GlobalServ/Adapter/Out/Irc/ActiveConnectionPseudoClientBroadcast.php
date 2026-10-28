<?php

declare(strict_types=1);

namespace App\GlobalServ\Adapter\Out\Irc;

use App\GlobalServ\Application\Port\Out\PseudoClientBroadcastPort;
use App\Irc\Application\Port\In\ActiveProtocolModuleHolderInterface;
use App\Irc\Application\Port\In\NetworkUserLookupPort;
use App\Irc\Application\Port\In\SendNoticePort;

/**
 * Introduces a temporary pseudo-client, broadcasts a NOTICE from it to every
 * connected user, then quits it.
 *
 * The whole lifecycle stays behind ProtocolServiceActionsInterface, so no raw
 * IRC line is ever emitted from GlobalServ.
 */
final readonly class ActiveConnectionPseudoClientBroadcast implements PseudoClientBroadcastPort
{
    private const string REALNAME = 'GlobalServ Notice';

    public function __construct(
        private ActiveProtocolModuleHolderInterface $connection,
        private GlobalServPseudoClientUidGenerator $uidGenerator,
        private NetworkUserLookupPort $users,
        private SendNoticePort $messages,
        private string $servicesVhost,
    ) {}

    public function broadcastFromPseudoClient(string $nick, string $message): ?int
    {
        $module = $this->connection->getProtocolModule();
        $serverSid = $this->connection->getServerSid();
        if (null === $module || null === $serverSid || !$this->connection->isConnected()) {
            return null;
        }

        $uid = $this->uidGenerator->generate();
        if (null === $uid) {
            return null;
        }

        $actions = $module->getServiceActions();
        $actions->introducePseudoClient($serverSid, $nick, $nick, $this->servicesVhost, $uid, self::REALNAME);

        $count = 0;
        foreach ($this->users->listConnectedUids() as $targetUid) {
            $this->messages->sendNotice($uid, $targetUid, $message);
            ++$count;
        }

        $actions->quitPseudoClient($serverSid, $uid, 'GlobalServ');

        return $count;
    }
}
