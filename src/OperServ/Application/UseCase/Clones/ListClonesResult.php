<?php

declare(strict_types=1);

namespace App\OperServ\Application\UseCase\Clones;

/** Sessions sharing the host of a connected nickname; the clones exclude the target itself. */
final readonly class ListClonesResult
{
    /** @param list<CloneSession> $clones */
    private function __construct(
        public bool $found,
        public string $nickname,
        public string $host,
        public int $sessionCount,
        public array $clones,
    ) {}

    /** @param list<CloneSession> $clones */
    public static function listed(string $nickname, string $host, int $sessionCount, array $clones): self
    {
        return new self(true, $nickname, $host, $sessionCount, $clones);
    }

    public static function notOnline(string $nickname): self
    {
        return new self(false, $nickname, '', 0, []);
    }
}
