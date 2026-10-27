<?php

declare(strict_types=1);

namespace App\GlobalServ\Application\Port\Out;

/**
 * Send a NOTICE from the GlobalServ pseudo-client to every member of a channel.
 *
 * Returns the recipient count, or null when the network is unavailable.
 */
interface ChannelMemberNoticePort
{
    public function sendToChannelMembers(string $channelName, string $message): ?int;
}
