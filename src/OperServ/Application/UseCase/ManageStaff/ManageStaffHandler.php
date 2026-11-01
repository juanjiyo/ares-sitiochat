<?php

declare(strict_types=1);

namespace App\OperServ\Application\UseCase\ManageStaff;

use App\OperServ\Application\Port\Out\OperatorAccountLookup;
use App\OperServ\Application\Port\Out\OperatorAssignmentStore;
use App\OperServ\Application\UseCase\ManageIrcop\IrcopAction;
use App\OperServ\Application\UseCase\ManageIrcop\IrcopListEntry;
use App\OperServ\Application\UseCase\ManageIrcop\IrcopOutcome;
use App\OperServ\Application\UseCase\ManageIrcop\ManageIrcop;
use App\OperServ\Application\UseCase\ManageIrcop\ManageIrcopHandlerInterface;
use App\OperServ\Domain\ValueObject\StaffRank;

/**
 * Staff management by rank (CeNTeR model): ADD gives a registered nick the
 * rank's role, with its permissions, operclass and vhost; DEL removes the
 * assignment and everything it granted. A nick holds a single rank: to change
 * it, DEL from the current rank first. Assignment, network effects and audit
 * are delegated to the IRCop assignment use case.
 */
final readonly class ManageStaffHandler
{
    public function __construct(
        private ManageIrcopHandlerInterface $ircops,
        private OperatorAccountLookup $accounts,
        private OperatorAssignmentStore $assignments,
    ) {}

    public function handle(ManageStaff $command): ManageStaffResult
    {
        return match ($command->action) {
            StaffAction::Add => $this->add($command),
            StaffAction::Delete => $this->delete($command),
            StaffAction::List => $this->list($command->rank),
            StaffAction::Unknown => new ManageStaffResult(ManageStaffOutcome::UnknownAction),
        };
    }

    private function add(ManageStaff $command): ManageStaffResult
    {
        if ('' === $command->nickname) {
            return new ManageStaffResult(ManageStaffOutcome::InvalidRequest);
        }

        $accountId = $this->accounts->findIdByNickname($command->nickname);
        $current = null === $accountId ? null : $this->assignments->findByNickId($accountId);
        if (null !== $current) {
            return new ManageStaffResult(ManageStaffOutcome::AlreadyStaff, $command->nickname, StaffRank::fromName($current->role->name));
        }

        $result = $this->ircops->handle($this->ircopCommand($command, IrcopAction::Add));

        return new ManageStaffResult(match ($result->outcome) {
            IrcopOutcome::Added => ManageStaffOutcome::Added,
            IrcopOutcome::NickNotActive => ManageStaffOutcome::NickNotActive,
            IrcopOutcome::RoleNotFound => ManageStaffOutcome::RankUnavailable,
            default => ManageStaffOutcome::NickNotRegistered,
        }, $command->nickname, $command->rank);
    }

    private function delete(ManageStaff $command): ManageStaffResult
    {
        if ('' === $command->nickname) {
            return new ManageStaffResult(ManageStaffOutcome::InvalidRequest);
        }

        $accountId = $this->accounts->findIdByNickname($command->nickname);
        $current = null === $accountId ? null : $this->assignments->findByNickId($accountId);
        if (null === $current || $current->role->name !== $command->rank->value) {
            return new ManageStaffResult(ManageStaffOutcome::NotThisRank, $command->nickname, null === $current ? null : StaffRank::fromName($current->role->name));
        }

        $this->ircops->handle($this->ircopCommand($command, IrcopAction::Delete));

        return new ManageStaffResult(ManageStaffOutcome::Deleted, $command->nickname, $command->rank);
    }

    private function list(StaffRank $rank): ManageStaffResult
    {
        $entries = [];
        foreach ($this->assignments->all() as $assignment) {
            if ($assignment->role->name === $rank->value) {
                $nickname = $this->accounts->findNicknameById($assignment->nickId) ?? (string) $assignment->nickId;
                $entries[] = new IrcopListEntry($nickname, $rank->value, $assignment->addedAt);
            }
        }

        return new ManageStaffResult(ManageStaffOutcome::Listed, currentRank: $rank, entries: $entries);
    }

    private function ircopCommand(ManageStaff $command, IrcopAction $action): ManageIrcop
    {
        return new ManageIrcop(
            $action,
            $command->actorNickname,
            $command->actorAccountId,
            $command->occurredAt,
            $command->nickname,
            $command->rank->value,
        );
    }
}
