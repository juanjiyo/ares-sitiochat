<?php

declare(strict_types=1);

namespace App\OperServ\Application\Security;

use function in_array;
use function str_ends_with;
use function strlen;
use function strtolower;
use function substr;

/**
 * Staff rank needed to set or unset a user mode through UMODE.
 *
 * An IRCop may only touch the modes of their own rank or below; Root may touch
 * any mode. The rank of an IRCop comes from the UnrealIRCd operclass of their
 * role: locop is a Pre-Operador, globop an Operador, admin and above an
 * Administrador.
 */
enum UserModeRank: int
{
    /** Any IRCop holding operserv.umode (Pre-Operador and up). */
    case Ircop = 0;

    case Operator = 1;

    case Administrator = 2;

    case Root = 3;

    /** Minimum rank per mode letter; letters not listed need Root. */
    private const array MINIMUM = [
        'i' => 0, 'w' => 0, 'x' => 0, 'p' => 0, 'R' => 0, 'Z' => 0, 'D' => 0, 'G' => 0, 'T' => 0, 'B' => 0, 'd' => 0, 'I' => 0,
        's' => 1, 'W' => 1,
        'H' => 2, 'q' => 2, 'o' => 2,
    ];

    /** Modes nobody may set or unset through UMODE, as in pulsar. */
    private const array FORBIDDEN = ['r', 'h'];

    public static function fromOperclass(?string $operclass): self
    {
        $operclass = strtolower($operclass ?? '');
        if (str_ends_with($operclass, '-with-override')) {
            $operclass = substr($operclass, 0, -strlen('-with-override'));
        }

        return match ($operclass) {
            'globop' => self::Operator,
            'admin', 'services-admin', 'netadmin' => self::Administrator,
            default => self::Ircop,
        };
    }

    public static function isForbidden(string $letter): bool
    {
        return in_array($letter, self::FORBIDDEN, true);
    }

    public static function requiredFor(string $letter): self
    {
        return self::from(self::MINIMUM[$letter] ?? self::Root->value);
    }
}
