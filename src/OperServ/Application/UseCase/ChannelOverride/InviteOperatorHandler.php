<?php

declare(strict_types=1);

namespace App\OperServ\Application\UseCase\ChannelOverride;

use App\OperServ\Application\Port\In\Audit\CommandAuditCategory;
use App\OperServ\Application\Port\In\Audit\CommandAuditRecord;
use App\OperServ\Application\Port\In\CommandAuditRecorder;
use App\OperServ\Application\Port\Out\ChannelOverrideActions;
use App\OperServ\Application\Port\Out\NetworkChannelLookup;
use App\OperServ\Application\Security\OperServPermission;

use function str_starts_with;

/** Orchestrates the INVITE channel override, which always invites the requesting operator. */
final readonly class InviteOperatorHandler
{
    public function __construct(
        private NetworkChannelLookup $channels,
        private ChannelOverrideActions $network,
        private CommandAuditRecorder $audit,
    ) {}

    public function handle(InviteOperator $command): ChannelOverrideResult
    {
        if (!str_starts_with($command->channelName, '#')) {
            return ChannelOverrideResult::refused(ChannelOverrideOutcome::InvalidChannel, $command->channelName, $command->actorNickname);
        }

        $channel = $this->channels->findByName($command->channelName);
        if (null === $channel) {
            return ChannelOverrideResult::refused(ChannelOverrideOutcome::ChannelNotFound, $command->channelName, $command->actorNickname);
        }

        if ($channel->hasMember($command->actorUid)) {
            return ChannelOverrideResult::refused(ChannelOverrideOutcome::AlreadyOnChannel, $command->channelName, $command->actorNickname);
        }

        if (!$this->network->invite($channel->name, $command->actorUid)) {
            return ChannelOverrideResult::refused(ChannelOverrideOutcome::NetworkUnavailable, $command->channelName, $command->actorNickname);
        }

        $this->audit->record(new CommandAuditRecord(
            category: CommandAuditCategory::OperatorAction,
            service: 'OperServ',
            actor: $command->actorNickname,
            operation: 'INVITE',
            occurredAt: $command->occurredAt,
            target: $channel->name,
            permission: OperServPermission::INVITE,
            metadata: ['channel' => $channel->name],
        ));

        return ChannelOverrideResult::applied($channel->name, $command->actorNickname);
    }
}
