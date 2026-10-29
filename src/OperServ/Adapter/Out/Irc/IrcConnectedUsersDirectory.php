<?php

declare(strict_types=1);

namespace App\OperServ\Adapter\Out\Irc;

use App\Irc\Application\Port\In\NetworkUserLookupPort;
use App\OperServ\Application\Port\Out\ConnectedUsersDirectory;

/** Walks the public Irc connected-user query to build the OperServ network snapshot. */
final readonly class IrcConnectedUsersDirectory implements ConnectedUsersDirectory
{
    public function __construct(private NetworkUserLookupPort $users) {}

    public function listConnected(): array
    {
        $connected = [];
        foreach ($this->users->listConnectedUids() as $uid) {
            $user = $this->users->findByUid($uid);
            if (null !== $user) {
                $connected[] = NetworkUserMapper::fromSenderView($user);
            }
        }

        return $connected;
    }
}
