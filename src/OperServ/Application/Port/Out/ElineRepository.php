<?php

declare(strict_types=1);

namespace App\OperServ\Application\Port\Out;

use DateTimeImmutable;

interface ElineRepository
{
    public function findByMask(string $mask): ?ElineEntry;

    /** @return list<ElineEntry> active at the given time, oldest first (the order LIST numbers them in) */
    public function findActiveAt(DateTimeImmutable $at): array;

    public function save(ElineEntry $entry): void;

    public function remove(string $mask): void;

    /** Removes the entries already expired at the given time; the IRCd expires its own copy. */
    public function removeExpiredAt(DateTimeImmutable $at): void;
}
