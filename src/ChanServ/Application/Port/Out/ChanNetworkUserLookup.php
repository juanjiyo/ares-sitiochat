<?php

declare(strict_types=1);

namespace App\ChanServ\Application\Port\Out;

use App\ChanServ\Application\Model\ChanNetworkUserView;

interface ChanNetworkUserLookup
{
    public function findByNick(string $nickname): ?ChanNetworkUserView;

    public function findByUid(string $uid): ?ChanNetworkUserView;
}
