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

use function str_starts_with;

/** Orchestrates the OP and DEOP channel overrides; only DEOP is refused on protected targets. */
final readonly class SetChannelOperatorHandler
{
    public function __construct(
        private NetworkChannelLookup $channels,
        private NetworkUserLookup $users,
        private ProtectedNetworkTargets $protectedTargets,
        private ChannelOverrideActions $network,
        private CommandAuditRecorder $audit,
    ) {}

    public function handle(SetChannelOperator $command): ChannelOverrideResult
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

        $protection = $command->grant ? null : $this->protectedTargets->protectionFor($target);
        if (null !== $protection) {
            return ChannelOverrideResult::refused(
                TargetProtection::Root === $protection ? ChannelOverrideOutcome::ProtectedRoot : ChannelOverrideOutcome::ProtectedIrcOperator,
                $command->channelName,
                $command->targetNickname,
            );
        }

        if (!$this->network->setOperatorStatus($channel->name, $target->uid, $command->grant)) {
            return ChannelOverrideResult::refused(ChannelOverrideOutcome::NetworkUnavailable, $command->channelName, $command->targetNickname);
        }

        $this->audit->record(new CommandAuditRecord(
            category: CommandAuditCategory::OperatorAction,
            service: 'OperServ',
            actor: $command->actorNickname,
            operation: $command->grant ? 'OP' : 'DEOP',
            occurredAt: $command->occurredAt,
            target: $target->nickname,
            permission: OperServPermission::OP,
            targetHost: $target->ident . '@' . $target->hostname,
            targetIp: $target->ipBase64,
            metadata: ['channel' => $channel->name],
        ));

        return ChannelOverrideResult::applied($channel->name, $target->nickname);
    }
}
