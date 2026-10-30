<?php

declare(strict_types=1);

namespace App\Tests\OperServ\Application\UseCase\ManageEline;

use App\OperServ\Application\Port\Out\ElineEntry;
use App\OperServ\Application\Port\Out\ElineRepository;
use DateTimeImmutable;

use function array_filter;
use function array_values;

final class InMemoryElineRepository implements ElineRepository
{
    /** @var array<string, ElineEntry> */
    public array $entries = [];

    public function findByMask(string $mask): ?ElineEntry
    {
        return $this->entries[$mask] ?? null;
    }

    public function findActiveAt(DateTimeImmutable $at): array
    {
        return array_values(array_filter($this->entries, static fn (ElineEntry $entry): bool => null === $entry->expiresAt || $entry->expiresAt > $at));
    }

    public function save(ElineEntry $entry): void
    {
        $this->entries[$entry->mask] = $entry;
    }

    public function remove(string $mask): void
    {
        unset($this->entries[$mask]);
    }

    public function removeExpiredAt(DateTimeImmutable $at): void
    {
        $this->entries = array_filter($this->entries, static fn (ElineEntry $entry): bool => null === $entry->expiresAt || $entry->expiresAt > $at);
    }
}
