<?php

declare(strict_types=1);

namespace App\OperServ\Application\UseCase\Clones;

use App\OperServ\Application\Port\In\Audit\CommandAuditCategory;
use App\OperServ\Application\Port\In\Audit\CommandAuditRecord;
use App\OperServ\Application\Port\In\CommandAuditRecorder;
use App\OperServ\Application\Port\Out\NetworkUserLookup;
use App\OperServ\Application\Security\OperServPermission;
use App\OperServ\Application\Security\ProtectedNetworkTargets;
use App\OperServ\Application\Security\TargetProtection;
use App\OperServ\Application\UseCase\ManageGline\GlineAction;
use App\OperServ\Application\UseCase\ManageGline\ManageGline;
use App\OperServ\Application\UseCase\ManageGline\ManageGlineHandlerInterface;
use App\OperServ\Application\UseCase\ManageGline\ManageGlineOutcome;

use function count;
use function trim;

/**
 * Orchestrates KILLCLONES and BLOCK: G-Lines *@host of a connected nickname through GLINE ADD, so the
 * ban is persisted, listed by GLINE LIST and removable with GLINE DEL. Loopback hosts and hosts shared
 * with a Root identity or an identified IRC operator with a role are never G-Lined.
 *
 * GLINE ADD also disconnects every session already on the host, as the original KILLCLONES and BLOCK did.
 */
final readonly class BanCloneHostHandler
{
    public function __construct(
        private NetworkUserLookup $users,
        private ConnectionHosts $hosts,
        private ProtectedNetworkTargets $protectedTargets,
        private ManageGlineHandlerInterface $glines,
        private CommandAuditRecorder $audit,
    ) {}

    public function handle(BanCloneHost $command): BanCloneHostResult
    {
        $target = $this->users->findByNickname($command->targetNickname);
        if (null === $target) {
            return BanCloneHostResult::refused(BanCloneHostOutcome::NotOnline, $command->targetNickname);
        }

        $host = $this->hosts->hostOf($target);
        if ($this->hosts->isLoopback($host)) {
            return BanCloneHostResult::refused(BanCloneHostOutcome::LoopbackHost, $target->nickname, $host);
        }

        $sessions = $this->hosts->sessionsOn($host);
        foreach ($sessions as $session) {
            $protection = $this->protectedTargets->protectionFor($session);
            if (null !== $protection) {
                $outcome = TargetProtection::Root === $protection ? BanCloneHostOutcome::ProtectedRoot : BanCloneHostOutcome::ProtectedIrcOperator;

                return BanCloneHostResult::refused($outcome, $target->nickname, $host, protectedNickname: $session->nickname);
            }
        }

        $mask = '*@' . $host;
        $duration = $command->ban->duration();
        $reason = $this->reasonFor($command, $target->nickname);
        $gline = $this->glines->handle(new ManageGline(
            GlineAction::Add,
            $command->actorNickname,
            $command->actorAccountId,
            $command->occurredAt,
            mask: $mask,
            expiry: $duration,
            reason: $reason,
        ));

        $refusal = match ($gline->outcome) {
            ManageGlineOutcome::Added => null,
            ManageGlineOutcome::AlreadyExists => BanCloneHostOutcome::AlreadyBanned,
            ManageGlineOutcome::InvalidMask, ManageGlineOutcome::GlobalMask, ManageGlineOutcome::DangerousMask => BanCloneHostOutcome::UnsafeHost,
            ManageGlineOutcome::ProtectedUser => BanCloneHostOutcome::ProtectedSubject,
            ManageGlineOutcome::LimitReached => BanCloneHostOutcome::LimitReached,
            default => BanCloneHostOutcome::Failed,
        };
        if (null !== $refusal) {
            return BanCloneHostResult::refused($refusal, $target->nickname, $host, $mask, $gline->protectedNickname, $gline->limit);
        }

        $this->audit->record(new CommandAuditRecord(
            category: CommandAuditCategory::OperatorAction,
            service: 'OperServ',
            actor: $command->actorNickname,
            operation: $command->ban->value,
            occurredAt: $command->occurredAt,
            target: $target->nickname,
            reason: $reason,
            permission: OperServPermission::CLONES,
            targetHost: $host,
            metadata: ['mask' => $mask, 'duration' => $duration, 'sessions' => count($sessions)],
        ));

        return BanCloneHostResult::banned($target->nickname, $host, $mask, $duration, $reason, count($sessions));
    }

    private function reasonFor(BanCloneHost $command, string $targetNickname): string
    {
        $reason = trim($command->reason);
        if ('' !== $reason) {
            return $reason;
        }

        return match ($command->ban) {
            CloneHostBan::KillClones => 'Clones of ' . $targetNickname,
            CloneHostBan::Block => 'Blocked by ' . $command->serviceNickname,
        };
    }
}
