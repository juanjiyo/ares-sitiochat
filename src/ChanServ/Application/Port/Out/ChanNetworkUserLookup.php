<?php

declare(strict_types=1);

namespace App\ChanServ\Application\Port\Out;

use App\Irc\Application\Port\In\SenderView;

interface ChanNetworkUserLookup
{
    public function findByNick(string $nickname): ?SenderView;

    public function findByUid(string $uid): ?SenderView;
}
