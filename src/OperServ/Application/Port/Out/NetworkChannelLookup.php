<?php

declare(strict_types=1);

namespace App\OperServ\Application\Port\Out;

/** Resolves a channel that currently exists on the network. */
interface NetworkChannelLookup
{
    public function findByName(string $channelName): ?NetworkChannel;
}
