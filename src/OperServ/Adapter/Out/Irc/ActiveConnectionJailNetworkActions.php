<?php

declare(strict_types=1);

namespace App\OperServ\Adapter\Out\Irc;

use App\Irc\Application\Port\In\ActiveProtocolModuleHolderInterface;
use App\Irc\Application\Port\In\ChannelLookupPort;
use App\OperServ\Application\Port\Out\JailNetworkActions;

/** Moves jailed users with SVSPART/SVSJOIN through the active protocol, reading channels from the network state. */
final readonly class ActiveConnectionJailNetworkActions implements JailNetworkActions
{
    public function __construct(
        private ActiveProtocolModuleHolderInterface $connection,
        private ChannelLookupPort $channels,
    ) {}

    public function channelsOf(string $uid): array
    {
        $channels = [];
        foreach ($this->channels->listAll() as $channel) {
            foreach ($channel->members as $member) {
                if ($member['uid'] === $uid) {
                    $channels[] = $channel->name;
                    break;
                }
            }
        }

        return $channels;
    }

    public function part(string $uid, string $channel): bool
    {
        $module = $this->connection->getProtocolModule();
        $serverSid = $this->connection->getServerSid();
        if (null === $module || null === $serverSid) {
            return false;
        }

        $module->getServiceActions()->forcePart($serverSid, $uid, $channel);

        return true;
    }

    public function join(string $uid, string $channel): bool
    {
        $module = $this->connection->getProtocolModule();
        $serverSid = $this->connection->getServerSid();
        if (null === $module || null === $serverSid) {
            return false;
        }

        $module->getServiceActions()->forceJoin($serverSid, $uid, $channel);

        return true;
    }
}
