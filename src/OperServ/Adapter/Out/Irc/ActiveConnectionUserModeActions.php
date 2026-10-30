<?php

declare(strict_types=1);

namespace App\OperServ\Adapter\Out\Irc;

use App\Irc\Application\Port\In\ActiveProtocolModuleHolderInterface;
use App\Irc\Application\Port\In\NetworkUserLookupPort;
use App\OperServ\Application\Port\Out\UserModeNetworkActions;

/** Sends the protocol-specific user mode change and mirrors it in the local network state. */
final readonly class ActiveConnectionUserModeActions implements UserModeNetworkActions
{
    public function __construct(
        private ActiveProtocolModuleHolderInterface $connection,
        private NetworkUserLookupPort $networkUsers,
    ) {}

    public function apply(string $targetUid, string $modes): bool
    {
        $module = $this->connection->getProtocolModule();
        $serverSid = $this->connection->getServerSid();
        if (null === $module || null === $serverSid) {
            return false;
        }

        $module->getServiceActions()->forceUserMode($serverSid, $targetUid, $modes);
        $this->networkUsers->applyModeChange($targetUid, $modes);

        return true;
    }
}
