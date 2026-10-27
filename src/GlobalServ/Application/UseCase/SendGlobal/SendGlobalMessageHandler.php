<?php

declare(strict_types=1);

namespace App\GlobalServ\Application\UseCase\SendGlobal;

use App\GlobalServ\Application\Model\BroadcastResult;
use App\GlobalServ\Application\Port\Out\MassBroadcastPort;

/** Broadcasts a GlobalServ GLOBAL message to every connected user. */
final readonly class SendGlobalMessageHandler
{
    public function __construct(private MassBroadcastPort $broadcast) {}

    public function handle(SendGlobalMessage $command): BroadcastResult
    {
        $count = $this->broadcast->broadcastToAll($command->message);

        return null === $count
            ? BroadcastResult::networkUnavailable()
            : BroadcastResult::sent($count);
    }
}
