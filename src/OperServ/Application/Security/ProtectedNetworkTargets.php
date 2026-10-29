<?php

declare(strict_types=1);

namespace App\OperServ\Application\Security;

use App\OperServ\Application\Port\Out\NetworkUser;
use App\OperServ\Application\Port\Out\OperatorAccountLookup;
use App\OperServ\Application\Port\Out\OperatorRoleAccess;
use App\OperServ\Application\Port\Out\RootIdentityRegistry;

use function strtolower;

/** Shields Root identities and identified IRC operators with an assigned role from punitive actions. */
final readonly class ProtectedNetworkTargets
{
    public function __construct(
        private RootIdentityRegistry $roots,
        private OperatorAccountLookup $nickAccounts,
        private OperatorRoleAccess $operatorRoles,
    ) {}

    public function protectionFor(NetworkUser $target): ?TargetProtection
    {
        if ($this->roots->contains($target->nickname)) {
            return TargetProtection::Root;
        }

        if (!$target->ircOperator || !$target->identified) {
            return null;
        }

        $accountId = $this->nickAccounts->findIdByNickname(strtolower($target->nickname));

        return null !== $accountId && $this->operatorRoles->hasAssignedRole($accountId) ? TargetProtection::IrcOperator : null;
    }
}
