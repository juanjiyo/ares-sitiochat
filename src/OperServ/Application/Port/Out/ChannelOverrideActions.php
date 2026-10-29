<?php

declare(strict_types=1);

namespace App\OperServ\Application\Port\Out;

/**
 * Applies IRCop channel overrides on the network once the application has authorised them.
 * Every method returns false when no active network protocol can perform the action.
 */
interface ChannelOverrideActions
{
    public function kick(string $channelName, string $targetUid, string $reason): bool;

    public function setOperatorStatus(string $channelName, string $targetUid, bool $grant): bool;

    /** @param list<string> $params */
    public function setModes(string $channelName, string $modes, array $params): bool;

    public function invite(string $channelName, string $targetUid): bool;
}
