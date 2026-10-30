<?php

declare(strict_types=1);

namespace App\OperServ\Application\Port\Out;

use DateTimeImmutable;

final readonly class ElineEntry
{
    public function __construct(
        public string $mask,
        public string $setBy,
        public string $reason,
        public DateTimeImmutable $createdAt,
        public ?DateTimeImmutable $expiresAt,
    ) {}
}
