<?php

declare(strict_types=1);

namespace App\OperServ\Application\UseCase\Clones;

/** Semantic outcome of KILLCLONES or BLOCK; IRC wording is left to the inbound adapter. */
final readonly class BanCloneHostResult
{
    private function __construct(
        public BanCloneHostOutcome $outcome,
        public string $nickname,
        public string $host,
        public string $mask,
        public string $duration,
        public string $reason,
        public int $sessionCount,
        public ?string $protectedNickname,
        public ?int $limit,
    ) {}

    public static function banned(string $nickname, string $host, string $mask, string $duration, string $reason, int $sessionCount): self
    {
        return new self(BanCloneHostOutcome::Banned, $nickname, $host, $mask, $duration, $reason, $sessionCount, null, null);
    }

    public static function refused(
        BanCloneHostOutcome $outcome,
        string $nickname,
        string $host = '',
        string $mask = '',
        ?string $protectedNickname = null,
        ?int $limit = null,
    ): self {
        return new self($outcome, $nickname, $host, $mask, '', '', 0, $protectedNickname, $limit);
    }
}
