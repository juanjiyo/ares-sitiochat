<?php

declare(strict_types=1);

namespace App\Irc\Application\Port\In;

/**
 * Password brute-force threshold enforced by the IRCd (UDB S::flood): after
 * $attempts failed passwords within $seconds the IRCd blocks the attempts and,
 * with password-flood-gline-time set, G-Lines the IP.
 */
interface PasswordFloodSettingPort
{
    /** @return bool false when the protocol store rejected the value */
    public function set(int $attempts, int $seconds): bool;
}
