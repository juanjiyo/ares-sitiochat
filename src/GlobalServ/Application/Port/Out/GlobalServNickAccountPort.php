<?php

declare(strict_types=1);

namespace App\GlobalServ\Application\Port\Out;

/** Nick registration facts GlobalServ needs for authorization decisions. */
interface GlobalServNickAccountPort
{
    public function findAccountIdByNick(string $nickname): ?int;
}
