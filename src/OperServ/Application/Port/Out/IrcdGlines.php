<?php

declare(strict_types=1);

namespace App\OperServ\Application\Port\Out;

/**
 * G-Lines the IRCd placed on its own (password flood protection of the UDB
 * module). The IRCd is their only owner: services list them and ask the IRCd
 * to remove them, but never store them.
 */
interface IrcdGlines
{
    /** @return list<IrcdGlineEntry> */
    public function all(): array;

    /** Asks the IRCd to remove the G-Line with that exact mask; false when there is none. */
    public function remove(string $mask): bool;
}
