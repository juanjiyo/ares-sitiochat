<?php

declare(strict_types=1);

namespace App\OperServ\Application\UseCase\Jail;

use DateTimeImmutable;

/** Typed intention to jail (or, with release, free) a connected user through OperServ. */
final readonly class JailUser
{
    public function __construct(
        public string $actorNickname,
        public string $targetNickname,
        public DateTimeImmutable $occurredAt,
    ) {}
}
