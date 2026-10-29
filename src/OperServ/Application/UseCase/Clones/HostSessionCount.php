<?php

declare(strict_types=1);

namespace App\OperServ\Application\UseCase\Clones;

final readonly class HostSessionCount
{
    public function __construct(
        public string $host,
        public int $sessions,
    ) {}
}
