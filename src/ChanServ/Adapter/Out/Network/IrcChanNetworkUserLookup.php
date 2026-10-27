<?php

declare(strict_types=1);

namespace App\ChanServ\Adapter\Out\Network;

use App\ChanServ\Application\Model\ChanNetworkUserView;
use App\ChanServ\Application\Port\Out\ChanNetworkUserLookup;
use App\Irc\Application\Port\In\NetworkUserLookupPort;

final readonly class IrcChanNetworkUserLookup implements ChanNetworkUserLookup
{
    public function __construct(private NetworkUserLookupPort $users) {}

    public function findByNick(string $nickname): ?ChanNetworkUserView
    {
        $user = $this->users->findByNick($nickname);

        return null === $user ? null : new ChanNetworkUserView($user->uid, $user->nick, $user->ident, $user->hostname);
    }

    public function findByUid(string $uid): ?ChanNetworkUserView
    {
        $user = $this->users->findByUid($uid);

        return null === $user ? null : new ChanNetworkUserView($user->uid, $user->nick, $user->ident, $user->hostname);
    }
}
