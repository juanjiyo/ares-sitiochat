<?php

declare(strict_types=1);

namespace App\OperServ\Application\UseCase\UserMode;

use DateTimeImmutable;

/** Typed intention to set or unset user modes on a connected user through OperServ. */
final readonly class ChangeUserModes
{
    public function __construct(
        public string $actorNickname,
        public string $targetNickname,
        public string $modes,
        public DateTimeImmutable $occurredAt,
    ) {}
}
