<?php

declare(strict_types=1);

namespace App\OperServ\Application\Port\Out;

use function in_array;

/** Live channel as OperServ needs it for channel overrides. */
final readonly class NetworkChannel
{
    /** @param list<string> $memberUids */
    public function __construct(
        public string $name,
        public array $memberUids,
    ) {}

    public function hasMember(string $uid): bool
    {
        return in_array($uid, $this->memberUids, true);
    }
}
