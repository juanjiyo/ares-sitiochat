<?php

declare(strict_types=1);

namespace App\GlobalServ\Application\UseCase\SendChannel;

use App\GlobalServ\Application\Model\BroadcastResult;
use App\GlobalServ\Application\Port\Out\ChannelMemberNoticePort;
use App\Irc\Application\Port\In\ChannelLookupPort;

/** Sends a GlobalServ CHAN message to every member of the target channel. */
final readonly class SendChannelMessageHandler
{
    public function __construct(
        private ChannelLookupPort $channels,
        private ChannelMemberNoticePort $channelNotice,
    ) {}

    public function handle(SendChannelMessage $command): BroadcastResult
    {
        if (null === $this->channels->findByChannelName($command->channelName)) {
            return BroadcastResult::channelNotFound($command->channelName);
        }

        $count = $this->channelNotice->sendToChannelMembers($command->channelName, $command->message);

        return null === $count
            ? BroadcastResult::networkUnavailable()
            : BroadcastResult::sent($count);
    }
}
