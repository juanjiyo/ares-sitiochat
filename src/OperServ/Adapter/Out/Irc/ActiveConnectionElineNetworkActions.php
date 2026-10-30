<?php

declare(strict_types=1);

namespace App\OperServ\Adapter\Out\Irc;

use App\Irc\Application\Port\In\ActiveProtocolModuleHolderInterface;
use App\OperServ\Application\Port\Out\ElineEntry;
use App\OperServ\Application\Port\Out\ElineNetworkActions;

use function explode;

/** Publishes E-Lines through the active protocol, exempting from the configured ban types. */
final readonly class ActiveConnectionElineNetworkActions implements ElineNetworkActions
{
    public function __construct(
        private ActiveProtocolModuleHolderInterface $connection,
        private string $banTypes,
    ) {}

    public function add(ElineEntry $entry): bool
    {
        $module = $this->connection->getProtocolModule();
        $serverSid = $this->connection->getServerSid();
        if (null === $module || null === $serverSid) {
            return false;
        }

        [$user, $host] = explode('@', $entry->mask, 2);
        $module->getServiceActions()->addBanException(
            $serverSid,
            $user,
            $host,
            $entry->setBy,
            $entry->createdAt->getTimestamp(),
            $entry->expiresAt?->getTimestamp() ?? 0,
            $this->banTypes,
            $entry->reason,
        );

        return true;
    }

    public function remove(string $mask, string $removedBy): void
    {
        $module = $this->connection->getProtocolModule();
        $serverSid = $this->connection->getServerSid();
        if (null === $module || null === $serverSid) {
            return;
        }

        [$user, $host] = explode('@', $mask, 2);
        $module->getServiceActions()->removeBanException($serverSid, $user, $host, $removedBy);
    }
}
