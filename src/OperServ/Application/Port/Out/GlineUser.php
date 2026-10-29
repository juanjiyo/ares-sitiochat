<?php

declare(strict_types=1);

namespace App\OperServ\Application\Port\Out;

final readonly class GlineUser
{
    /** @param string $ipAddress real IP of the connection, '' when unknown */
    public function __construct(public string $nickname, public string $ident, public string $hostname, public string $ipAddress = '') {}
}
