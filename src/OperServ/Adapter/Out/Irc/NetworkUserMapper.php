<?php

declare(strict_types=1);

namespace App\OperServ\Adapter\Out\Irc;

use App\Irc\Application\Port\In\SenderView;
use App\OperServ\Application\Port\Out\NetworkUser;

/** Maps the public Irc user view to the OperServ consumer-owned NetworkUser. */
final class NetworkUserMapper
{
    private function __construct() {}

    public static function fromSenderView(SenderView $user): NetworkUser
    {
        return new NetworkUser(
            $user->uid,
            $user->nick,
            $user->ident,
            $user->hostname,
            $user->ipBase64,
            $user->isIdentified,
            $user->isOper,
        );
    }
}
