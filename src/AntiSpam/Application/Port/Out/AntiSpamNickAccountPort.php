<?php

declare(strict_types=1);

namespace App\AntiSpam\Application\Port\Out;

/** Nick registration facts AntiSpam needs for authorization decisions. */
interface AntiSpamNickAccountPort
{
    public function findAccountIdByNick(string $nickname): ?int;
}
