<?php

declare(strict_types=1);

namespace App\OperServ\Application\UseCase\Kill;

use App\OperServ\Application\Port\In\Audit\CommandAuditCategory;
use App\OperServ\Application\Port\In\Audit\CommandAuditRecord;
use App\OperServ\Application\Port\In\CommandAuditRecorder;
use App\OperServ\Application\Port\Out\KillNetworkUser as KillNetworkUserPort;
use App\OperServ\Application\Port\Out\NetworkUserLookup;
use App\OperServ\Application\Security\ProtectedNetworkTargets;
use App\OperServ\Application\Security\TargetProtection;

use function sprintf;

/** Orchestrates KILL without knowing IRC command syntax or presentation. */
final readonly class KillNetworkUserHandler
{
    public function __construct(
        private NetworkUserLookup $users,
        private ProtectedNetworkTargets $protectedTargets,
        private KillNetworkUserPort $network,
        private CommandAuditRecorder $audit,
    ) {}

    public function handle(KillNetworkUser $command): KillNetworkUserResult
    {
        $target = $this->users->findByNickname($command->targetNickname);
        if (null === $target) {
            return KillNetworkUserResult::notOnline($command->targetNickname);
        }

        $protection = $this->protectedTargets->protectionFor($target);
        if (TargetProtection::Root === $protection) {
            return KillNetworkUserResult::protectedRoot($command->targetNickname);
        }

        if (TargetProtection::IrcOperator === $protection) {
            return KillNetworkUserResult::protectedIrcOperator($command->targetNickname);
        }

        $killReason = sprintf('Killed (%s: %s): %s', $command->serviceNickname, $command->actorNickname, $command->reason);
        if (!$this->network->kill($target->uid, $killReason)) {
            return KillNetworkUserResult::networkUnavailable($command->targetNickname);
        }

        $this->audit->record(new CommandAuditRecord(
            category: CommandAuditCategory::OperatorAction,
            service: 'OperServ',
            actor: $command->actorNickname,
            operation: 'KILL',
            occurredAt: $command->occurredAt,
            target: $command->targetNickname,
            reason: $command->reason,
            permission: 'operserv.kill',
            targetHost: $target->ident . '@' . $target->hostname,
            targetIp: $target->ipBase64,
        ));

        return KillNetworkUserResult::killed($command->targetNickname, $command->reason);
    }
}
