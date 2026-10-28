<?php

declare(strict_types=1);

namespace App\GlobalServ\Adapter\Out\Irc;

use App\GlobalServ\Application\Port\Out\GlobalServNetworkIdentity;
use App\Irc\Application\Port\In\ServiceUidRegistry;

/**
 * Resolves the GlobalServ pseudo-client UID through the shared service UID registry.
 */
final readonly class ServiceRegistryGlobalServIdentity implements GlobalServNetworkIdentity
{
    public function __construct(
        private ServiceUidRegistry $serviceUids,
    ) {}

    public function serviceUid(): ?string
    {
        $uid = $this->serviceUids->getUid('globalserv');

        return '' === $uid ? null : $uid;
    }
}
