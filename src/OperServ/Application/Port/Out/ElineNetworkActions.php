<?php

declare(strict_types=1);

namespace App\OperServ\Application\Port\Out;

/** Publishes ban exceptions on the network after the application has authorised them. */
interface ElineNetworkActions
{
    /** Returns false when no active network protocol can perform the action. */
    public function add(ElineEntry $entry): bool;

    public function remove(string $mask, string $removedBy): void;
}
