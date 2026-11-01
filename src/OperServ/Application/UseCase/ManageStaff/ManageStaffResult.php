<?php

declare(strict_types=1);

namespace App\OperServ\Application\UseCase\ManageStaff;

use App\OperServ\Application\UseCase\ManageIrcop\IrcopListEntry;
use App\OperServ\Domain\ValueObject\StaffRank;

final readonly class ManageStaffResult
{
    /** @param list<IrcopListEntry> $entries */
    public function __construct(
        public ManageStaffOutcome $outcome,
        public string $nickname = '',
        public ?StaffRank $currentRank = null,
        public array $entries = [],
    ) {}
}
