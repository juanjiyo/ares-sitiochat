<?php

declare(strict_types=1);

namespace App\OperServ\Application\UseCase\ChannelOverride;

use App\OperServ\Application\Port\In\Audit\CommandAuditCategory;
use App\OperServ\Application\Port\In\Audit\CommandAuditRecord;
use App\OperServ\Application\Port\In\CommandAuditRecorder;
use App\OperServ\Application\Port\Out\ChannelOverrideActions;
use App\OperServ\Application\Port\Out\NetworkChannelLookup;
use App\OperServ\Application\Port\Out\NetworkUserLookup;
use App\OperServ\Application\Security\OperServPermission;
use App\OperServ\Application\Security\ProtectedNetworkTargets;
use App\OperServ\Application\Security\TargetProtection;

use function sprintf;
use function str_starts_with;
use function trim;

/** Orchestrates the KICK channel override without knowing IRC command syntax or presentation. */
final readonly class KickChannelMemberHandler
{
    public function __construct(
        private NetworkChannelLookup $channels,
        private NetworkUserLookup $users,
        private ProtectedNetworkTargets $protectedTargets,
        private ChannelOverrideActions $network,
        private CommandAuditRecorder $audit,
    ) {}

    public function handle(KickChannelMember $command): ChannelOverrideResult
    {
        if (!str_starts_with($command->channelName, '#')) {
            return ChannelOverrideResult::refused(ChannelOverrideOutcome::InvalidChannel, $command->channelName, $command->targetNickname);
        }

        $channel = $this->channels->findByName($command->channelName);
        if (null === $channel) {
            return ChannelOverrideResult::refused(ChannelOverrideOutcome::ChannelNotFound, $command->channelName, $command->targetNickname);
        }

        $target = $this->users->findByNickname($command->targetNickname);
        if (null === $target) {
            return ChannelOverrideResult::refused(ChannelOverrideOutcome::NotOnline, $command->channelName, $command->targetNickname);
        }

        if (!$channel->hasMember($target->uid)) {
            return ChannelOverrideResult::refused(ChannelOverrideOutcome::NotOnChannel, $command->channelName, $command->targetNickname);
        }

        $protection = $this->protectedTargets->protectionFor($target);
        if (null !== $protection) {
            return ChannelOverrideResult::refused(
                TargetProtection::Root === $protection ? ChannelOverrideOutcome::ProtectedRoot : ChannelOverrideOutcome::ProtectedIrcOperator,
                $command->channelName,
                $command->targetNickname,
            );
        }

        $reason = '' === trim($command->reason) ? sprintf('Kicked by %s', $command->serviceNickname) : $command->reason;
        if (!$this->network->kick($channel->name, $target->uid, $reason)) {
            return ChannelOverrideResult::refused(ChannelOverrideOutcome::NetworkUnavailable, $command->channelName, $command->targetNickname);
        }

        $this->audit->record(new CommandAuditRecord(
            category: CommandAuditCategory::OperatorAction,
            service: 'OperServ',
            actor: $command->actorNickname,
            operation: 'KICK',
            occurredAt: $command->occurredAt,
            target: $target->nickname,
            reason: $reason,
            permission: OperServPermission::KICK,
            targetHost: $target->ident . '@' . $target->hostname,
            targetIp: $target->ipBase64,
            metadata: ['channel' => $channel->name],
        ));

        return ChannelOverrideResult::applied($channel->name, $target->nickname, $reason);
    }
}
