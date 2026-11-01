<?php

declare(strict_types=1);

namespace App\OperServ\Application\UseCase\ManageSpamfilter;

use App\OperServ\Application\Port\Out\SpamfilterEntry;

final readonly class ManageSpamfilterResult
{
    /** @param array<int, SpamfilterEntry> $entries listed entries keyed by their number (as DEL takes it) */
    public function __construct(
        public ManageSpamfilterOutcome $outcome,
        public ?SpamfilterEntry $entry = null,
        public array $entries = [],
        public ?string $item = null,
    ) {}
}
