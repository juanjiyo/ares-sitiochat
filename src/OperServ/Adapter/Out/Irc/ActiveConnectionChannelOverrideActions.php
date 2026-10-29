<?php

declare(strict_types=1);

namespace App\OperServ\Adapter\Out\Irc;

use App\Irc\Application\Port\In\ActiveProtocolModuleHolderInterface;
use App\Irc\Application\Port\In\ChannelLookupPort;
use App\Irc\Application\Port\In\ProtocolServiceActionsInterface;
use App\Irc\Application\Port\In\ServiceUidRegistry;
use App\OperServ\Application\Port\Out\ChannelOverrideActions;
use Closure;

/**
 * Performs channel overrides through the active protocol with OperServ as the source.
 * Falls back to the services server as source while the OperServ client is not introduced.
 */
final readonly class ActiveConnectionChannelOverrideActions implements ChannelOverrideActions
{
    private const string SERVICE_KEY = 'operserv';

    private const string OPERATOR_MODE = 'o';

    public function __construct(
        private ActiveProtocolModuleHolderInterface $connection,
        private ServiceUidRegistry $serviceUids,
        private ChannelLookupPort $channels,
    ) {}

    public function kick(string $channelName, string $targetUid, string $reason): bool
    {
        return $this->perform(static function (ProtocolServiceActionsInterface $actions, string $serverSid, string $sourceUid) use ($channelName, $targetUid, $reason): void {
            $actions->kickFromChannel($serverSid, $channelName, $targetUid, $reason, $sourceUid);
        });
    }

    public function setOperatorStatus(string $channelName, string $targetUid, bool $grant): bool
    {
        return $this->perform(function (ProtocolServiceActionsInterface $actions, string $serverSid, string $sourceUid) use ($channelName, $targetUid, $grant): void {
            $actions->setChannelMemberMode($serverSid, $channelName, $targetUid, self::OPERATOR_MODE, $grant, $sourceUid, $this->timestampOf($channelName));
        });
    }

    public function setModes(string $channelName, string $modes, array $params): bool
    {
        return $this->perform(function (ProtocolServiceActionsInterface $actions, string $serverSid, string $sourceUid) use ($channelName, $modes, $params): void {
            $actions->setChannelModes($serverSid, $channelName, $modes, $params, $sourceUid, $this->timestampOf($channelName));
        });
    }

    public function invite(string $channelName, string $targetUid): bool
    {
        return $this->perform(function (ProtocolServiceActionsInterface $actions, string $serverSid, string $sourceUid) use ($channelName, $targetUid): void {
            $actions->inviteUserToChannel($serverSid, $channelName, $targetUid, $sourceUid, $this->timestampOf($channelName));
        });
    }

    /** @param Closure(ProtocolServiceActionsInterface, string, string): void $action */
    private function perform(Closure $action): bool
    {
        $module = $this->connection->getProtocolModule();
        $serverSid = $this->connection->getServerSid();
        if (null === $module || null === $serverSid) {
            return false;
        }

        $action($module->getServiceActions(), $serverSid, $this->serviceUids->getUid(self::SERVICE_KEY) ?? '');

        return true;
    }

    /** Channel creation TS for protocols that validate it (InspIRCd); null when unknown. */
    private function timestampOf(string $channelName): ?int
    {
        $timestamp = $this->channels->findByChannelName($channelName)->timestamp ?? 0;

        return 0 < $timestamp ? $timestamp : null;
    }
}
