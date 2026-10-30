<?php

declare(strict_types=1);

namespace App\OperServ\Application\UseCase\ManageEline;

use App\OperServ\Application\Port\Out\ElineEntry;

/** Semantic outcome of ELINE; IRC wording is left to the inbound adapter. */
final readonly class ManageElineResult
{
    /**
     * @param list<ElineEntry> $entries the added entry, the deleted ones, or the listed ones
     * @param string           $detail  the rejected mask, expiry or item
     */
    public function __construct(
        public ManageElineOutcome $outcome,
        public array $entries = [],
        public string $detail = '',
    ) {}
}
