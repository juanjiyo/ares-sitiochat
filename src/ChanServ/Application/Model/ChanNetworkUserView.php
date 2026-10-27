<?php

declare(strict_types=1);

namespace App\ChanServ\Application\Model;

final readonly class ChanNetworkUserView
{
    public function __construct(
        public string $uid,
        public string $nick,
        public string $ident,
        public string $hostname,
    ) {}
}
