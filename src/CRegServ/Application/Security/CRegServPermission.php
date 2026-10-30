<?php

declare(strict_types=1);

namespace App\CRegServ\Application\Security;

/**
 * Permissions owned by the CReG service.
 *
 * Every permission is an IRCop permission, mirroring how ChanServ exposes its own
 * (see ChanServPermission::allIrcop): ares has no services-admin tier distinct from
 * IRCop, so pulsar's is_services_admin commands map to IRCop permissions here too.
 */
final readonly class CRegServPermission
{
    public const string APROBAR = 'cregserv.aprobar';

    public const string DENEGAR = 'cregserv.denegar';

    public const string PENDIENTES = 'cregserv.pendientes';

    public const string DENEGADOS = 'cregserv.denegados';

    public const string DROP = 'cregserv.drop';

    /**
     * Moved from ChanServ (chanserv.saregister): the oper's force-registration path
     * follows the rest of the CReG command set over to this service.
     */
    public const string SAREGISTER = 'cregserv.saregister';

    /** Marks and unmarks Interés General (IG) channels. */
    public const string IG = 'cregserv.ig';

    /** Takes an IG channel over for the administration (APODERA). */
    public const string APODERA = 'cregserv.apodera';

    /**
     * @return list<string>
     */
    public static function allIrcop(): array
    {
        return [
            self::APROBAR,
            self::DENEGAR,
            self::PENDIENTES,
            self::DENEGADOS,
            self::DROP,
            self::SAREGISTER,
            self::IG,
            self::APODERA,
        ];
    }

    private function __construct() {}
}
