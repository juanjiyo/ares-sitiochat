<?php

declare(strict_types=1);

namespace App\OperServ\Application\UseCase\Jail;

use App\OperServ\Application\Port\In\Audit\CommandAuditCategory;
use App\OperServ\Application\Port\In\Audit\CommandAuditRecord;
use App\OperServ\Application\Port\In\CommandAuditRecorder;
use App\OperServ\Application\Port\Out\JailNetworkActions;
use App\OperServ\Application\Port\Out\NetworkUser;
use App\OperServ\Application\Port\Out\NetworkUserLookup;
use App\OperServ\Application\Security\OperServPermission;
use App\OperServ\Application\Security\ProtectedNetworkTargets;
use App\OperServ\Application\Security\TargetProtection;

use function strcasecmp;

/**
 * JAIL and UNJAIL: a jailed user is moved out of every channel into the jail
 * channel and kept there by EnforceJail until released or disconnected.
 */
final readonly class JailUserHandler
{
    public function __construct(
        private NetworkUserLookup $users,
        private ProtectedNetworkTargets $protectedTargets,
        private JailNetworkActions $network,
        private JailRoster $roster,
        private CommandAuditRecorder $audit,
        private string $jailChannel,
    ) {}

    public function jailChannel(): string
    {
        return $this->jailChannel;
    }

    public function jail(JailUser $command): JailResult
    {
        $target = $this->users->findByNickname($command->targetNickname);
        if (null === $target) {
            return $this->result(JailOutcome::NotOnline, $command->targetNickname);
        }

        $protection = $this->protectedTargets->protectionFor($target);
        if (null !== $protection) {
            return $this->result(TargetProtection::Root === $protection ? JailOutcome::ProtectedRoot : JailOutcome::ProtectedIrcOperator, $target->nickname);
        }

        if ($this->roster->contains($target->uid)) {
            return $this->result(JailOutcome::AlreadyJailed, $target->nickname);
        }

        if (!$this->network->join($target->uid, $this->jailChannel)) {
            return $this->result(JailOutcome::NetworkUnavailable, $target->nickname);
        }

        foreach ($this->network->channelsOf($target->uid) as $channel) {
            if (0 !== strcasecmp($channel, $this->jailChannel)) {
                $this->network->part($target->uid, $channel);
            }
        }

        $this->roster->jail($target->uid);
        $this->record('JAIL', $command, $target);

        return $this->result(JailOutcome::Jailed, $target->nickname);
    }

    public function release(JailUser $command): JailResult
    {
        $target = $this->users->findByNickname($command->targetNickname);
        if (null === $target) {
            return $this->result(JailOutcome::NotOnline, $command->targetNickname);
        }

        if (!$this->roster->release($target->uid)) {
            return $this->result(JailOutcome::NotJailed, $target->nickname);
        }

        $this->record('UNJAIL', $command, $target);

        return $this->result(JailOutcome::Released, $target->nickname);
    }

    private function result(JailOutcome $outcome, string $nickname): JailResult
    {
        return new JailResult($outcome, $nickname, $this->jailChannel);
    }

    private function record(string $operation, JailUser $command, NetworkUser $target): void
    {
        $this->audit->record(new CommandAuditRecord(
            category: CommandAuditCategory::OperatorAction,
            service: 'OperServ',
            actor: $command->actorNickname,
            operation: $operation,
            occurredAt: $command->occurredAt,
            target: $target->nickname,
            permission: OperServPermission::JAIL,
            targetHost: $target->ident . '@' . $target->hostname,
            targetIp: $target->ipBase64,
            metadata: ['channel' => $this->jailChannel],
        ));
    }
}
