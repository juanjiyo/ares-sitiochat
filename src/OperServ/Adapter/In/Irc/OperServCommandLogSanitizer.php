<?php

declare(strict_types=1);

namespace App\OperServ\Adapter\In\Irc;

use function is_string;
use function preg_split;
use function strtoupper;
use function trim;

use const PREG_SPLIT_NO_EMPTY;

/**
 * Produces a bounded command identifier without retaining IRC arguments: the
 * registered name of the command the first word names (aliases included), or
 * UNKNOWN. Reading the registry keeps new commands from logging as UNKNOWN.
 */
final class OperServCommandLogSanitizer
{
    private function __construct() {}

    public static function commandName(string $text, OperServCommandRegistry $commands): string
    {
        $parts = preg_split('/\s+/', trim($text), 2, PREG_SPLIT_NO_EMPTY) ?: [];
        $command = $commands->find(is_string($parts[0] ?? null) ? $parts[0] : '');

        return null === $command ? 'UNKNOWN' : strtoupper($command->getName());
    }
}
