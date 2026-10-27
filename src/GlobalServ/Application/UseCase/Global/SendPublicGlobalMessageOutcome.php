<?php

declare(strict_types=1);

namespace App\GlobalServ\Application\UseCase\Global;

enum SendPublicGlobalMessageOutcome: string
{
    case Sent = 'sent';
    case NetworkUnavailable = 'network_unavailable';
}
