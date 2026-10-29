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

    private function __construct() {}
}
