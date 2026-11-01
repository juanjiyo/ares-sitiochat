<?php

declare(strict_types=1);

namespace App\Irc\Application\Port\In;

/**
 * Read-only view of the password-flood G-Lines owned by the IRCd. Services
 * never store them; they can only ask the IRCd to remove one.
 */
interface PasswordFloodGlinePort
{
    /** @return list<PasswordFloodGline> active entries, oldest first */
    public function list(): array;

    /** Asks the IRCd to remove the G-Line from the whole network, naming who removed it. */
    public function remove(PasswordFloodGline $gline, string $removedBy): void;
}
