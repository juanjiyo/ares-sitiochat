<?php

declare(strict_types=1);

namespace App\OperServ\Application\UseCase\UserMode;

use App\OperServ\Application\Port\In\Audit\CommandAuditCategory;
use App\OperServ\Application\Port\In\Audit\CommandAuditRecord;
use App\OperServ\Application\Port\In\CommandAuditRecorder;
use App\OperServ\Application\Port\Out\NetworkUserLookup;
use App\OperServ\Application\Port\Out\OperatorAccountLookup;
use App\OperServ\Application\Port\Out\OperatorRoleAccess;
use App\OperServ\Application\Port\Out\OperatorRoleStore;
use App\OperServ\Application\Port\Out\RootIdentityRegistry;
use App\OperServ\Application\Port\Out\UserModeNetworkActions;
use App\OperServ\Application\Security\OperServPermission;
use App\OperServ\Application\Security\ProtectedNetworkTargets;
use App\OperServ\Application\Security\TargetProtection;
use App\OperServ\Application\Security\UserModeRank;

use function array_filter;
use function array_unique;
use function implode;
use function preg_match;
use function str_replace;
use function str_split;
use function strcasecmp;
use function strtolower;

/**
 * Orchestrates UMODE: any IRCop with the permission may change user modes, but
 * only those of their own rank or below; Root may change any of them.
 */
final readonly class ChangeUserModesHandler
{
    public function __construct(
        private NetworkUserLookup $users,
        private RootIdentityRegistry $roots,
        private OperatorAccountLookup $accounts,
        private OperatorRoleAccess $roles,
        private OperatorRoleStore $roleStore,
        private ProtectedNetworkTargets $protectedTargets,
        private UserModeNetworkActions $network,
        private CommandAuditRecorder $audit,
    ) {}

    public function handle(ChangeUserModes $command): UserModeResult
    {
        if (1 !== preg_match('/^(?:[+-][a-zA-Z]+)+$/', $command->modes)) {
            return new UserModeResult(UserModeOutcome::InvalidModes, $command->targetNickname, $command->modes);
        }

        $letters = str_split(str_replace(['+', '-'], '', $command->modes));
        if ([] !== array_filter($letters, UserModeRank::isForbidden(...))) {
            return new UserModeResult(UserModeOutcome::ForbiddenModes, $command->targetNickname);
        }

        $target = $this->users->findByNickname($command->targetNickname);
        if (null === $target) {
            return new UserModeResult(UserModeOutcome::NotOnline, $command->targetNickname);
        }

        $rank = $this->rankOf($command->actorNickname);

        if (UserModeRank::Root !== $rank && 0 !== strcasecmp($target->nickname, $command->actorNickname)) {
            $protection = $this->protectedTargets->protectionFor($target);
            if (null !== $protection) {
                return new UserModeResult(
                    TargetProtection::Root === $protection ? UserModeOutcome::ProtectedRoot : UserModeOutcome::ProtectedIrcOperator,
                    $target->nickname,
                );
            }
        }

        $above = array_unique(array_filter($letters, static fn (string $letter): bool => UserModeRank::requiredFor($letter)->value > $rank->value));
        if ([] !== $above) {
            return new UserModeResult(UserModeOutcome::AboveRank, $target->nickname, implode('', $above));
        }

        if (!$this->network->apply($target->uid, $command->modes)) {
            return new UserModeResult(UserModeOutcome::NetworkUnavailable, $target->nickname);
        }

        $this->audit->record(new CommandAuditRecord(
            category: CommandAuditCategory::OperatorAction,
            service: 'OperServ',
            actor: $command->actorNickname,
            operation: 'UMODE',
            occurredAt: $command->occurredAt,
            target: $target->nickname,
            permission: OperServPermission::UMODE,
            targetHost: $target->ident . '@' . $target->hostname,
            targetIp: $target->ipBase64,
            metadata: ['modes' => $command->modes],
        ));

        return new UserModeResult(UserModeOutcome::Applied, $target->nickname, $command->modes);
    }

    private function rankOf(string $nickname): UserModeRank
    {
        if ($this->roots->contains($nickname)) {
            return UserModeRank::Root;
        }

        $accountId = $this->accounts->findIdByNickname(strtolower($nickname));
        $roleName = null === $accountId ? null : $this->roles->roleName($accountId);
        $role = null === $roleName ? null : $this->roleStore->findByName($roleName);

        return UserModeRank::fromOperclass($role?->operclass);
    }
}
