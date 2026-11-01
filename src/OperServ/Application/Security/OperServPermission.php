<?php

declare(strict_types=1);

namespace App\OperServ\Application\Security;

final class OperServPermission
{
    public const string KILL = 'operserv.kill';

    public const string GLINE = 'operserv.gline';

    public const string GEOIP = 'operserv.geoip';

    public const string RAW = 'operserv.raw';

    public const string MOTD = 'operserv.motd';

    public const string KICK = 'operserv.kick';

    /** Grants both the OP and DEOP channel overrides. */
    public const string OP = 'operserv.op';

    /** Grants both the MODE and LIMPIA channel overrides. */
    public const string MODE = 'operserv.mode';

    public const string INVITE = 'operserv.invite';

    /** Grants LISTCLONES, KILLCLONES and BLOCK. */
    public const string CLONES = 'operserv.clones';

    public const string CONEXIONES = 'operserv.conexiones';

    /** Changes user modes, limited to the modes of the actor's rank. */
    public const string UMODE = 'operserv.umode';

    /** Grants both JAIL and UNJAIL. */
    public const string JAIL = 'operserv.jail';

    /** Ban exceptions by IP (E-Lines). */
    public const string ELINE = 'operserv.eline';

    private function __construct() {}
}
