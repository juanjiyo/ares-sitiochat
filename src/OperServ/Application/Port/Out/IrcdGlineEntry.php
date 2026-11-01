<?php

declare(strict_types=1);

namespace App\OperServ\Application\Port\Out;

use DateTimeImmutable;

/** A G-Line owned by the IRCd (never stored by services), shown and removable from OperServ. */
final readonly class IrcdGlineEntry
{
    public function __construct(
        public string $mask,
        public string $reason,
        public ?DateTimeImmutable $expiresAt,
    ) {}
}
