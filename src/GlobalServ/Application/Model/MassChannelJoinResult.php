<?php

declare(strict_types=1);

namespace App\GlobalServ\Application\Model;

final readonly class MassChannelJoinResult
{
    private function __construct(
        public bool $supported,
        public ?int $joinedCount = null,
    ) {}

    public static function supported(int $joinedCount): self
    {
        return new self(true, $joinedCount);
    }

    public static function unsupported(): self
    {
        return new self(false);
    }
}
