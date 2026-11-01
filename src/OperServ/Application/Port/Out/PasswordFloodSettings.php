<?php

declare(strict_types=1);

namespace App\OperServ\Application\Port\Out;

/** Network-wide password brute-force threshold (failed attempts per period). */
interface PasswordFloodSettings
{
    /** @return bool false when the network rejected the value */
    public function set(int $attempts, int $seconds): bool;
}
