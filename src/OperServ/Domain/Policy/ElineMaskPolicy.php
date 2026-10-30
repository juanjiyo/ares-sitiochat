<?php

declare(strict_types=1);

namespace App\OperServ\Domain\Policy;

use function explode;
use function fnmatch;
use function inet_pton;
use function intdiv;
use function ord;
use function str_contains;
use function strlen;
use function strtolower;
use function substr;

/** Tells whether an E-Line mask (user@IP or user@CIDR) covers a connection. */
final class ElineMaskPolicy
{
    private function __construct() {}

    public static function covers(string $mask, string $ident, string $ipAddress): bool
    {
        if (!str_contains($mask, '@')) {
            return false;
        }

        [$userPattern, $range] = explode('@', $mask, 2);
        if (!fnmatch(strtolower($userPattern), strtolower($ident))) {
            return false;
        }

        $ip = @inet_pton($ipAddress);
        if (false === $ip) {
            return false;
        }

        [$network, $bits] = str_contains($range, '/') ? explode('/', $range, 2) : [$range, null];
        $base = @inet_pton($network);
        if (false === $base || strlen($base) !== strlen($ip)) {
            return false;
        }

        $prefix = null === $bits ? strlen($ip) * 8 : (int) $bits;
        $bytes = intdiv($prefix, 8);
        if (substr($ip, 0, $bytes) !== substr($base, 0, $bytes)) {
            return false;
        }

        $rest = $prefix % 8;
        if (0 === $rest) {
            return true;
        }

        $bitMask = (0xFF << (8 - $rest)) & 0xFF;

        return (ord($ip[$bytes]) & $bitMask) === (ord($base[$bytes]) & $bitMask);
    }
}
