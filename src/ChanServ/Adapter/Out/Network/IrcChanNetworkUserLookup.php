<?php

declare(strict_types=1);

namespace App\ChanServ\Adapter\Out\Network;

use App\ChanServ\Application\Port\Out\ChanNetworkUserLookup;
use App\Irc\Application\Port\In\NetworkUserLookupPort;
use App\Irc\Application\Port\In\SenderView;

final readonly class IrcChanNetworkUserLookup implements ChanNetworkUserLookup
{
    public function __construct(private NetworkUserLookupPort $users) {}

    public function findByNick(string $nickname): ?SenderView
    {
        return $this->users->findByNick($nickname);
    }

    public function findByUid(string $uid): ?SenderView
    {
        return $this->users->findByUid($uid);
    }
}
