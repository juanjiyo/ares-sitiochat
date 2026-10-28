<?php

declare(strict_types=1);

namespace App\GlobalServ\Adapter\Out\Irc;

use App\GlobalServ\Application\Model\MassChannelJoinResult;
use App\GlobalServ\Application\Port\Out\GlobalServNetworkIdentity;
use App\GlobalServ\Application\Port\Out\MassChannelJoinPort;
use App\Irc\Application\Port\In\ActiveProtocolModuleHolderInterface;
use App\Irc\Application\Port\In\NetworkUserLookupPort;

/**
 * Force-joins every connected user through the protocol's SVSJOIN-equivalent
 * action (SAJOIN for Unreal, SVSJOIN for InspIRCd).
 */
final readonly class ActiveConnectionMassChannelJoin implements MassChannelJoinPort
{
    public function __construct(
        private ActiveProtocolModuleHolderInterface $connection,
        private GlobalServNetworkIdentity $identity,
        private NetworkUserLookupPort $users,
    ) {}

    public function joinAllUsersToChannel(string $channelName, string $reason): MassChannelJoinResult
    {
        if (!$this->connection->isConnected()) {
            return MassChannelJoinResult::unsupported();
        }

        $module = $this->connection->getProtocolModule();
        $serverSid = $this->connection->getServerSid();

        if (null === $module || null === $serverSid) {
            return MassChannelJoinResult::unsupported();
        }

        $serviceUid = $this->identity->serviceUid() ?? '';
        $count = 0;
        foreach ($this->users->listConnectedUids() as $targetUid) {
            $module->getServiceActions()->forceJoin($serverSid, $targetUid, $channelName, $serviceUid);
            ++$count;
        }

        return MassChannelJoinResult::supported($count);
    }
}
