<?php

declare(strict_types=1);

namespace App\OperServ\Adapter\In\Event;

use App\OperServ\Domain\ValueObject\StaffRank;

use function array_filter;
use function array_map;
use function array_values;
use function explode;
use function trim;

/** The channels a staff rank sits in, from the configured channel lists. */
final class StaffChannels
{
    /**
     * @param string $staffChannels comma-separated channels OPER, ADMIN and ROOT sit in
     * @param string $adminChannels comma-separated channels ADMIN and ROOT also sit in
     *
     * @return list<string>
     */
    public static function forRank(StaffRank $rank, string $staffChannels, string $adminChannels): array
    {
        $channels = $rank->joinsStaffChannels() ? self::split($staffChannels) : [];

        return $rank->joinsAdminChannels() ? [...$channels, ...self::split($adminChannels)] : $channels;
    }

    /** @return list<string> */
    private static function split(string $channels): array
    {
        return array_values(array_filter(array_map(trim(...), explode(',', $channels)), static fn (string $c): bool => '' !== $c));
    }
}
