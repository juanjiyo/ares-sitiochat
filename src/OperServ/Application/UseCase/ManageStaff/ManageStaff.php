<?php

declare(strict_types=1);

namespace App\OperServ\Application\UseCase\ManageStaff;

use App\OperServ\Domain\ValueObject\StaffRank;
use DateTimeImmutable;

/** <RANK> ADD|DEL <nick> or <RANK> LIST, as in the dbots CeNTeR module. */
final readonly class ManageStaff
{
    public function __construct(
        public StaffRank $rank,
        public StaffAction $action,
        public string $actorNickname,
        public ?int $actorAccountId,
        public DateTimeImmutable $occurredAt,
        public string $nickname = '',
    ) {}
}
