<?php

declare(strict_types=1);

namespace App\GlobalServ\Application\UseCase\Global;

use App\GlobalServ\Application\Port\Out\GlobalBroadcastPort;
use App\GlobalServ\Application\Port\Out\GlobalServNetworkIdentity;

/** Orchestrates a public GLOBAL broadcast through the consumer-owned broadcast port. */
final readonly class SendPublicGlobalMessageHandler implements SendPublicGlobalMessageHandlerInterface
{
    public function __construct(
        private GlobalServNetworkIdentity $identity,
        private GlobalBroadcastPort $broadcast,
    ) {}

    public function handle(SendPublicGlobalMessage $command): SendPublicGlobalMessageResult
    {
        $serviceUid = $this->identity->serviceUid();

        if (null === $serviceUid) {
            return SendPublicGlobalMessageResult::networkUnavailable();
        }

        $count = $this->broadcast->broadcastNotice($serviceUid, $command->message);

        return null === $count
            ? SendPublicGlobalMessageResult::networkUnavailable()
            : SendPublicGlobalMessageResult::sent($count);
    }
}
