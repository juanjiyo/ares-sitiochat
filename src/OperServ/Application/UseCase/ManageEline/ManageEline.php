<?php

declare(strict_types=1);

namespace App\OperServ\Application\UseCase\ManageEline;

use DateTimeImmutable;

/**
 * Typed ELINE request. For Add, $target is the mask and $expiry the optional "+30d"
 * style duration; for Delete, $target is a mask, an entry number or a list like 1,3-5.
 */
final readonly class ManageEline
{
    public function __construct(
        public ElineAction $action,
        public string $actorNickname,
        public DateTimeImmutable $occurredAt,
        public string $target = '',
        public string $reason = '',
        public ?string $expiry = null,
    ) {}
}
