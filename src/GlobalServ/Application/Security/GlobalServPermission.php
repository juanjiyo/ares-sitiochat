<?php

declare(strict_types=1);

namespace App\GlobalServ\Application\Security;

/**
 * Permissions owned by the GlobalServ service.
 *
 * Every permission is an IRCop permission, mirroring how CRegServ exposes its own
 * (see CRegServPermission::allIrcop).
 */
final readonly class GlobalServPermission
{
    /** Broadcast a message to every connected user (GLOBAL). */
    public const string GLOBAL = 'globalserv.global';

    /** Introduce a temporary pseudo-client and broadcast (NICK). */
    public const string NICK = 'globalserv.nick';

    /** Broadcast to every member of a channel (CHAN). */
    public const string CHAN = 'globalserv.chan';

    /** Force-join every user into a channel (JOIN). */
    public const string JOIN = 'globalserv.join';

    /** Broadcast to Pre-Operador and above (PRE-OPER). */
    public const string PREOPER = 'globalserv.preoper';

    /** Broadcast to Operador and above (OPER). */
    public const string OPER = 'globalserv.oper';

    /** Broadcast to Administrador only (ADMIN). */
    public const string ADMIN = 'globalserv.admin';

    /**
     * @return list<string>
     */
    public static function allIrcop(): array
    {
        return [
            self::GLOBAL,
            self::NICK,
            self::CHAN,
            self::JOIN,
            self::PREOPER,
            self::OPER,
            self::ADMIN,
        ];
    }

    private function __construct() {}
}
