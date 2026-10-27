<?php

declare(strict_types=1);

namespace App\GlobalServ\Adapter\Out\Irc;

use App\GlobalServ\Application\Port\Out\ChannelMemberNoticePort;
use App\GlobalServ\Application\Port\Out\GlobalServNetworkIdentity;
use App\Irc\Application\Port\In\ActiveConnectionHolderInterface;
use App\Irc\Application\Port\In\ChannelLookupPort;
use App\Irc\Application\Port\In\SendNoticePort;

/**
 * Sends a GlobalServ NOTICE to every member of a channel through the shared
 * notice port. Channel membership comes from the IRC core projection, so this
 * adapter never touches IRC entities.
 */
final readonly class ActiveConnectionChannelMemberNotice implements ChannelMemberNoticePort
{
    public function __construct(
        private ActiveConnectionHolderInterface $connection,
        private ChannelLookupPort $channels,
        private GlobalServNetworkIdentity $identity,
        private SendNoticePort $messages,
    ) {}

    public function sendToChannelMembers(string $channelName, string $message): ?int
    {
        if (!$this->connection->isConnected()) {
            return null;
        }

        $serviceUid = $this->identity->serviceUid();
        if (null === $serviceUid) {
            return null;
        }

        $channel = $this->channels->findByChannelName($channelName);
        if (null === $channel) {
            return null;
        }

        $count = 0;
        foreach ($channel->members as $member) {
            $this->messages->sendNotice($serviceUid, $member['uid'], $message);
            ++$count;
        }

        return $count;
    }
}
