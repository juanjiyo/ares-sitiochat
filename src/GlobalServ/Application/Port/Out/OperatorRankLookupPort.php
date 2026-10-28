<?php

declare(strict_types=1);

namespace App\GlobalServ\Application\Port\Out;

use App\GlobalServ\Domain\ValueObject\GlobalServRank;

/**
 * Check whether a connected user has at least the given staff rank.
 *
 * Returns null when the rank cannot be determined by the current adapter.
 */
interface OperatorRankLookupPort
{
    public function hasAtLeastRank(string $uid, GlobalServRank $rank): ?bool;
}
