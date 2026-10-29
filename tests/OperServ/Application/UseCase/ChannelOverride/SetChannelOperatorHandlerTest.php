<?php

declare(strict_types=1);

namespace App\Tests\OperServ\Application\UseCase\ChannelOverride;

use App\OperServ\Application\Port\In\Audit\CommandAuditCategory;
use App\OperServ\Application\Port\Out\NetworkChannel;
use App\OperServ\Application\Port\Out\NetworkChannelLookup;
use App\OperServ\Application\Port\Out\NetworkUser;
use App\OperServ\Application\Port\Out\NetworkUserLookup;
use App\OperServ\Application\Port\Out\OperatorAccountLookup;
use App\OperServ\Application\Port\Out\OperatorRoleAccess;
use App\OperServ\Application\Port\Out\RootIdentityRegistry;
use App\OperServ\Application\Security\OperServPermission;
use App\OperServ\Application\Security\ProtectedNetworkTargets;
use App\OperServ\Application\UseCase\ChannelOverride\ChannelOverrideOutcome;
use App\OperServ\Application\UseCase\ChannelOverride\ChannelOverrideResult;
use App\OperServ\Application\UseCase\ChannelOverride\SetChannelOperator;
use App\OperServ\Application\UseCase\ChannelOverride\SetChannelOperatorHandler;
use DateTimeImmutable;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(SetChannelOperator::class)]
#[CoversClass(SetChannelOperatorHandler::class)]
#[CoversClass(ChannelOverrideResult::class)]
#[CoversClass(NetworkChannel::class)]
final class SetChannelOperatorHandlerTest extends TestCase
{
    /** @return iterable<string, array{bool, string, string}> */
    public static function directions(): iterable
    {
        yield 'op' => [true, '+o', 'OP'];
        yield 'deop' => [false, '-o', 'DEOP'];
    }

    #[Test]
    #[DataProvider('directions')]
    public function changesOperatorStatusAndRecordsAudit(bool $grant, string $mode, string $operation): void
    {
        $network = new RecordingChannelOverrideActions();
        $audit = new RecordingCommandAuditRecorder();
        $at = new DateTimeImmutable('2026-10-29T10:00:00+00:00');

        $result = $this->handler($network, $audit)->handle(new SetChannelOperator('Oper', '#Help', 'target', $grant, $at));

        self::assertSame(ChannelOverrideOutcome::Applied, $result->outcome);
        self::assertSame('#help', $result->channelName);
        self::assertSame('Target', $result->targetNickname);
        self::assertSame([['operator', '#help', ['UID1', $mode]]], $network->calls);
        self::assertCount(1, $audit->records);
        $record = $audit->records[0];
        self::assertSame(CommandAuditCategory::OperatorAction, $record->category);
        self::assertSame('OperServ', $record->service);
        self::assertSame('Oper', $record->actor);
        self::assertSame($operation, $record->operation);
        self::assertSame($at, $record->occurredAt);
        self::assertSame('Target', $record->target);
        self::assertNull($record->reason);
        self::assertSame(OperServPermission::OP, $record->permission);
        self::assertSame('ident@host.test', $record->targetHost);
        self::assertSame('encoded-ip', $record->targetIp);
        self::assertSame(['channel' => '#help'], $record->metadata);
    }

    #[Test]
    public function opIsAllowedOnProtectedTargets(): void
    {
        $network = new RecordingChannelOverrideActions();

        $result = $this->handler($network, new RecordingCommandAuditRecorder(), root: true)
            ->handle(new SetChannelOperator('Oper', '#help', 'Target', true, new DateTimeImmutable()));

        self::assertSame(ChannelOverrideOutcome::Applied, $result->outcome);
        self::assertSame([['operator', '#help', ['UID1', '+o']]], $network->calls);
    }

    /** @return iterable<string, array{string, bool, bool, bool, bool, bool, ChannelOverrideOutcome}> */
    public static function refusals(): iterable
    {
        yield 'channel without prefix' => ['help', true, true, true, false, false, ChannelOverrideOutcome::InvalidChannel];
        yield 'channel not on network' => ['#help', false, true, true, false, false, ChannelOverrideOutcome::ChannelNotFound];
        yield 'target offline' => ['#help', true, false, true, false, false, ChannelOverrideOutcome::NotOnline];
        yield 'target not on channel' => ['#help', true, true, false, false, false, ChannelOverrideOutcome::NotOnChannel];
        yield 'deop root' => ['#help', true, true, true, true, false, ChannelOverrideOutcome::ProtectedRoot];
        yield 'deop ircop with role' => ['#help', true, true, true, false, true, ChannelOverrideOutcome::ProtectedIrcOperator];
    }

    #[Test]
    #[DataProvider('refusals')]
    public function deopRefusesWithoutNetworkEffectOrAudit(string $channel, bool $exists, bool $online, bool $member, bool $root, bool $operatorWithRole, ChannelOverrideOutcome $expected): void
    {
        $network = new RecordingChannelOverrideActions();
        $audit = new RecordingCommandAuditRecorder();

        $result = $this->handler($network, $audit, $exists, $online, $member, $root, $operatorWithRole)
            ->handle(new SetChannelOperator('Oper', $channel, 'Target', false, new DateTimeImmutable()));

        self::assertSame($expected, $result->outcome);
        self::assertSame($channel, $result->channelName);
        self::assertSame('Target', $result->targetNickname);
        self::assertSame([], $network->calls);
        self::assertSame([], $audit->records);
    }

    #[Test]
    public function reportsUnavailableNetworkWithoutAudit(): void
    {
        $network = new RecordingChannelOverrideActions(false);
        $audit = new RecordingCommandAuditRecorder();

        $result = $this->handler($network, $audit)->handle(new SetChannelOperator('Oper', '#help', 'Target', true, new DateTimeImmutable()));

        self::assertSame(ChannelOverrideOutcome::NetworkUnavailable, $result->outcome);
        self::assertCount(1, $network->calls);
        self::assertSame([], $audit->records);
    }

    private function handler(
        RecordingChannelOverrideActions $network,
        RecordingCommandAuditRecorder $audit,
        bool $channelExists = true,
        bool $online = true,
        bool $member = true,
        bool $root = false,
        bool $operatorWithRole = false,
    ): SetChannelOperatorHandler {
        $channels = $this->createStub(NetworkChannelLookup::class);
        $channels->method('findByName')->willReturn($channelExists ? new NetworkChannel('#help', $member ? ['UID1'] : []) : null);
        $users = $this->createStub(NetworkUserLookup::class);
        $users->method('findByNickname')->willReturn($online ? new NetworkUser('UID1', 'Target', 'ident', 'host.test', 'encoded-ip', $operatorWithRole, $operatorWithRole) : null);
        $roots = $this->createStub(RootIdentityRegistry::class);
        $roots->method('contains')->willReturn($root);
        $accounts = $this->createStub(OperatorAccountLookup::class);
        $accounts->method('findIdByNickname')->willReturn(7);
        $roles = $this->createStub(OperatorRoleAccess::class);
        $roles->method('hasAssignedRole')->willReturn($operatorWithRole);

        return new SetChannelOperatorHandler($channels, $users, new ProtectedNetworkTargets($roots, $accounts, $roles), $network, $audit);
    }
}
