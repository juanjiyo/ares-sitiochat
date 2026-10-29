<?php

declare(strict_types=1);

namespace App\OperServ\Application\UseCase\Clones;

use DateTimeImmutable;

final readonly class BanCloneHost
{
    public function __construct(
        public CloneHostBan $ban,
        public string $actorNickname,
        public ?int $actorAccountId,
        public string $serviceNickname,
        public string $targetNickname,
        public string $reason,
        public DateTimeImmutable $occurredAt,
    ) {}
}
