<?php

declare(strict_types=1);

namespace App\GlobalServ\Application\UseCase\SendNick;

use App\GlobalServ\Application\Model\BroadcastResult;
use App\GlobalServ\Application\Port\Out\PseudoClientBroadcastPort;

/** Introduces a temporary pseudo-client, broadcasts a message, then quits it. */
final readonly class SendNickBroadcastHandler
{
    public function __construct(private PseudoClientBroadcastPort $broadcast) {}

    public function handle(SendNickBroadcast $command): BroadcastResult
    {
        $count = $this->broadcast->broadcastFromPseudoClient($command->nick, $command->message);

        return null === $count
            ? BroadcastResult::networkUnavailable()
            : BroadcastResult::sent($count);
    }
}
