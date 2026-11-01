<?php

declare(strict_types=1);

namespace App\Irc\Application\Port\In;

/**
 * Global spamfilters owned by the IRCd. Services list them and ask the IRCd to
 * add or remove one, but never store them (the IRCd persists them itself).
 */
interface SpamfilterPort
{
    /** @return list<Spamfilter> global spamfilters, oldest first */
    public function list(): array;

    /** @return bool false when there is no link to the IRCd */
    public function add(Spamfilter $spamfilter): bool;

    /** @return bool false when there is no link to the IRCd */
    public function remove(Spamfilter $spamfilter, string $removedBy): bool;
}
