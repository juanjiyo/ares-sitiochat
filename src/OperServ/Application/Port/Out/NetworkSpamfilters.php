<?php

declare(strict_types=1);

namespace App\OperServ\Application\Port\Out;

/**
 * Global spamfilters owned by the IRCd: services list them and ask the IRCd to
 * add or remove one, but never store them.
 */
interface NetworkSpamfilters
{
    /** @return list<SpamfilterEntry> oldest first */
    public function all(): array;

    /** @return bool false when there is no link to the IRCd */
    public function add(SpamfilterEntry $entry): bool;

    /** @return bool false when there is no link to the IRCd */
    public function remove(SpamfilterEntry $entry, string $removedBy): bool;
}
