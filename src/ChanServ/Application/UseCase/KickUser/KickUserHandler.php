<?php

declare(strict_types=1);

namespace App\ChanServ\Application\UseCase\KickUser;

use App\ChanServ\Application\Port\Out\ChanNetworkActions;
use App\ChanServ\Application\Port\Out\ChanNetworkUserLookup;
use App\ChanServ\Application\Port\Out\RegisteredChannelRepositoryInterface;

use function strtolower;

final readonly class KickUserHandler
{
    public function __construct(
        private RegisteredChannelRepositoryInterface $channels,
        private ChanNetworkActions $network,
        private ChanNetworkUserLookup $users,
    ) {}

    public function handle(KickUser $command): KickUserResult
    {
        if (null === $this->channels->findByChannelName(strtolower($command->channelName))) {
            return KickUserResult::channelNotRegistered($command->channelName);
        }
        if (!$this->network->isChannelOnNetwork($command->channelName)) {
            return KickUserResult::channelNotOnNetwork($command->channelName);
        }

        $target = $this->users->findByNick($command->targetNickname);
        if (null === $target) {
            return KickUserResult::targetNotConnected($command->targetNickname);
        }

        $this->network->kickFromChannel($command->channelName, $target->uid, $command->reason);

        return KickUserResult::kicked($command->channelName, $command->targetNickname);
    }
}
