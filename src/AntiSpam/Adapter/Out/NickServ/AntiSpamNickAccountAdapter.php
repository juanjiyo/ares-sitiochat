<?php

declare(strict_types=1);

namespace App\AntiSpam\Adapter\Out\NickServ;

use App\AntiSpam\Application\Port\Out\AntiSpamNickAccountPort;
use App\NickServ\Application\Port\In\NickAccountQuery;

final readonly class AntiSpamNickAccountAdapter implements AntiSpamNickAccountPort
{
    public function __construct(
        private NickAccountQuery $nickAccountQuery,
    ) {}

    public function findAccountIdByNick(string $nickname): ?int
    {
        return $this->nickAccountQuery->findIdByNick($nickname);
    }
}
