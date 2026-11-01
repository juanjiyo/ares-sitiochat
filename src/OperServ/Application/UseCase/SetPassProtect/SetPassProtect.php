<?php

declare(strict_types=1);

namespace App\OperServ\Application\UseCase\SetPassProtect;

use DateTimeImmutable;

/** SET PASSPROTECT <attempts:seconds>: password brute-force threshold. */
final readonly class SetPassProtect
{
    public function __construct(
        public string $actor,
        public DateTimeImmutable $occurredAt,
        public string $value,
    ) {}
}
