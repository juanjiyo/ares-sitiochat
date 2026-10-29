<?php

declare(strict_types=1);

namespace App\OperServ\Application\UseCase\NetworkInfo;

use App\OperServ\Application\Port\Out\ConnectedUsersDirectory;

use function count;

/** Answers STATS: how many users are connected to the network right now. */
final readonly class CountOnlineUsersHandler
{
    public function __construct(private ConnectedUsersDirectory $users) {}

    public function handle(): int
    {
        return count($this->users->listConnected());
    }
}
