<?php

declare(strict_types=1);

namespace App\OperServ\Application\UseCase\ChannelOverride;

use App\OperServ\Application\Port\In\Audit\CommandAuditCategory;
use App\OperServ\Application\Port\In\Audit\CommandAuditRecord;
use App\OperServ\Application\Port\In\CommandAuditRecorder;
use App\OperServ\Application\Port\Out\ChannelOverrideActions;
use App\OperServ\Application\Port\Out\NetworkChannelLookup;
use App\OperServ\Application\Security\OperServPermission;

use function implode;
use function preg_match;
use function str_starts_with;

/** Orchestrates the MODE and LIMPIA channel overrides without knowing IRC command syntax. */
final readonly class SetChannelModesHandler
{
    /** A mode change starts with a sign and holds only signs and mode letters, at least one of them a letter. */
    private const string MODE_CHANGE_PATTERN = '/^[+-](?=.*[A-Za-z])[+\-A-Za-z]+$/';

    public function __construct(
        private NetworkChannelLookup $channels,
        private ChannelOverrideActions $network,
        private CommandAuditRecorder $audit,
    ) {}

    public function handle(SetChannelModes $command): ChannelOverrideResult
    {
        if (!str_starts_with($command->channelName, '#')) {
            return ChannelOverrideResult::refused(ChannelOverrideOutcome::InvalidChannel, $command->channelName);
        }

        $channel = $this->channels->findByName($command->channelName);
        if (null === $channel) {
            return ChannelOverrideResult::refused(ChannelOverrideOutcome::ChannelNotFound, $command->channelName);
        }

        if (1 !== preg_match(self::MODE_CHANGE_PATTERN, $command->modes)) {
            return ChannelOverrideResult::refused(ChannelOverrideOutcome::InvalidModes, $command->channelName, detail: $command->modes);
        }

        if (!$this->network->setModes($channel->name, $command->modes, $command->params)) {
            return ChannelOverrideResult::refused(ChannelOverrideOutcome::NetworkUnavailable, $command->channelName);
        }

        $modeLine = implode(' ', [$command->modes, ...$command->params]);
        $this->audit->record(new CommandAuditRecord(
            category: CommandAuditCategory::OperatorAction,
            service: 'OperServ',
            actor: $command->actorNickname,
            operation: $command->cleanup ? 'LIMPIA' : 'MODE',
            occurredAt: $command->occurredAt,
            target: $channel->name,
            permission: OperServPermission::MODE,
            metadata: ['channel' => $channel->name, 'modes' => $modeLine],
        ));

        return ChannelOverrideResult::applied($channel->name, detail: $modeLine);
    }
}
