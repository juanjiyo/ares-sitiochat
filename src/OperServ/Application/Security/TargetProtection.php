<?php

declare(strict_types=1);

namespace App\OperServ\Application\Security;

/** Reason why a connected user cannot be the target of a punitive operator action. */
enum TargetProtection: string
{
    case Root = 'root';
    case IrcOperator = 'irc_operator';
}
