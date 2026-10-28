<?php

declare(strict_types=1);

namespace App\GlobalServ\Adapter\Out\NickServ;

use App\GlobalServ\Application\Port\Out\GlobalServNickAccountPort;
use App\NickServ\Application\Port\In\NickAccountQuery;

final readonly class GlobalServNickAccountAdapter implements GlobalServNickAccountPort
{
    public function __construct(
        private NickAccountQuery $nickAccountQuery,
    ) {}

    public function findAccountIdByNick(string $nickname): ?int
    {
        return $this->nickAccountQuery->findIdByNick($nickname);
    }
}
