<?php

declare(strict_types=1);

namespace App\OperServ\Application\Port\Out;

/** Moves a jailed user between channels after the application has authorised it. */
interface JailNetworkActions
{
    /** @return list<string> the channels the user is currently in */
    public function channelsOf(string $uid): array;

    /** Returns false when no active network protocol can perform the action. */
    public function part(string $uid, string $channel): bool;

    /** Returns false when no active network protocol can perform the action. */
    public function join(string $uid, string $channel): bool;
}
