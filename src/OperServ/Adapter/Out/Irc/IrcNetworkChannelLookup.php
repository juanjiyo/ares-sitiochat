<?php

declare(strict_types=1);

namespace App\OperServ\Adapter\Out\Irc;

use App\Irc\Application\Port\In\ChannelLookupPort;
use App\OperServ\Application\Port\Out\NetworkChannel;
use App\OperServ\Application\Port\Out\NetworkChannelLookup;

use function array_column;

/** Adapts the public Irc channel query to the OperServ consumer-owned port. */
final readonly class IrcNetworkChannelLookup implements NetworkChannelLookup
{
    public function __construct(private ChannelLookupPort $channels) {}

    public function findByName(string $channelName): ?NetworkChannel
    {
        $channel = $this->channels->findByChannelName($channelName);

        return null === $channel ? null : new NetworkChannel($channel->name, array_column($channel->members, 'uid'));
    }
}
