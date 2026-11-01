<?php

declare(strict_types=1);

namespace App\OperServ\Application\UseCase\SetPassProtect;

use App\OperServ\Application\Port\In\Audit\CommandAuditCategory;
use App\OperServ\Application\Port\In\Audit\CommandAuditRecord;
use App\OperServ\Application\Port\In\CommandAuditRecorder;
use App\OperServ\Application\Port\Out\PasswordFloodSettings;
use App\OperServ\Application\Security\OperServPermission;

use function preg_match;

/**
 * Sets how many failed passwords per period the IRCd tolerates from one IP
 * before blocking (and, when the IRCd is configured for it, G-Lining) it.
 */
final readonly class SetPassProtectHandler
{
    private const int MAX = 2147483647;

    public function __construct(
        private PasswordFloodSettings $settings,
        private CommandAuditRecorder $audit,
    ) {}

    public function handle(SetPassProtect $command): SetPassProtectOutcome
    {
        if (1 !== preg_match('/^([0-9]{1,10}):([0-9]{1,10})\z/', $command->value, $parts)) {
            return SetPassProtectOutcome::InvalidValue;
        }
        $attempts = (int) $parts[1];
        $seconds = (int) $parts[2];
        if ($attempts < 1 || $seconds < 1 || $attempts > self::MAX || $seconds > self::MAX) {
            return SetPassProtectOutcome::InvalidValue;
        }

        if (!$this->settings->set($attempts, $seconds)) {
            return SetPassProtectOutcome::Rejected;
        }

        $this->audit->record(new CommandAuditRecord(
            category: CommandAuditCategory::OperatorAction,
            service: 'OperServ',
            actor: $command->actor,
            operation: 'SET PASSPROTECT',
            occurredAt: $command->occurredAt,
            target: $attempts . ':' . $seconds,
            permission: OperServPermission::SET,
        ));

        return SetPassProtectOutcome::Set;
    }
}
