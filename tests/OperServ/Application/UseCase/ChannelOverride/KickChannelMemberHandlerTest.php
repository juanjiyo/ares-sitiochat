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
use App\OperServ\Application\UseCase\ChannelOverride\KickChannelMember;
use App\OperServ\Application\UseCase\ChannelOverride\KickChannelMemberHandler;
use DateTimeImmutable;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

use function in_array;
use function strtolower;

#[CoversClass(KickChannelMember::class)]
#[CoversClass(KickChannelMemberHandler::class)]
#[CoversClass(ChannelOverrideResult::class)]
#[CoversClass(NetworkChannel::class)]
final class KickChannelMemberHandlerTest extends TestCase
{
    #[Test]
    public function kicksMemberWithGivenReasonAndRecordsAudit(): void
    {
        $network = new RecordingChannelOverrideActions();
        $audit = new RecordingCommandAuditRecorder();
        $at = new DateTimeImmutable('2026-10-29T10:00:00+00:00');

        $result = $this->handler($network, $audit, member: true)
            ->handle(new KickChannelMember('Oper', 'OperServ', '#Help', 'target', 'flooding', $at));

        self::assertSame(ChannelOverrideOutcome::Applied, $result->outcome);
        self::assertSame('#help', $result->channelName);
        self::assertSame('Target', $result->targetNickname);
        self::assertSame('flooding', $result->detail);
        self::assertSame([['kick', '#help', ['UID1', 'flooding']]], $network->calls);
        self::assertCount(1, $audit->records);
        $record = $audit->records[0];
        self::assertSame(CommandAuditCategory::OperatorAction, $record->category);
        self::assertSame('OperServ', $record->service);
        self::assertSame('Oper', $record->actor);
        self::assertSame('KICK', $record->operation);
        self::assertSame($at, $record->occurredAt);
        self::assertSame('Target', $record->target);
        self::assertSame('flooding', $record->reason);
        self::assertSame(OperServPermission::KICK, $record->permission);
        self::assertSame('ident@host.test', $record->targetHost);
        self::assertSame('encoded-ip', $record->targetIp);
        self::assertSame(['channel' => '#help'], $record->metadata);
    }

    #[Test]
    public function usesServiceReasonWhenNoReasonIsGiven(): void
    {
        $network = new RecordingChannelOverrideActions();
        $audit = new RecordingCommandAuditRecorder();

        $result = $this->handler($network, $audit, member: true)
            ->handle(new KickChannelMember('Oper', 'OperServ', '#help', 'Target', '  ', new DateTimeImmutable()));

        self::assertSame('Kicked by OperServ', $result->detail);
        self::assertSame([['kick', '#help', ['UID1', 'Kicked by OperServ']]], $network->calls);
        self::assertSame('Kicked by OperServ', $audit->records[0]->reason);
    }

    /** @return iterable<string, array{string, bool, bool, bool, list<string>, ChannelOverrideOutcome}> */
    public static function refusals(): iterable
    {
        yield 'channel without prefix' => ['help', true, true, true, [], ChannelOverrideOutcome::InvalidChannel];
        yield 'channel not on network' => ['#help', false, true, true, [], ChannelOverrideOutcome::ChannelNotFound];
        yield 'target offline' => ['#help', true, false, true, [], ChannelOverrideOutcome::NotOnline];
        yield 'target not on channel' => ['#help', true, true, false, [], ChannelOverrideOutcome::NotOnChannel];
        yield 'root target' => ['#help', true, true, true, ['target'], ChannelOverrideOutcome::ProtectedRoot];
    }

    /** @param list<string> $roots */
    #[Test]
    #[DataProvider('refusals')]
    public function refusesWithoutNetworkEffectOrAudit(string $channel, bool $channelExists, bool $online, bool $member, array $roots, ChannelOverrideOutcome $expected): void
    {
        $network = new RecordingChannelOverrideActions();
        $audit = new RecordingCommandAuditRecorder();

        $result = $this->handler($network, $audit, $channelExists, $online, $member, $roots)
            ->handle(new KickChannelMember('Oper', 'OperServ', $channel, 'Target', 'reason', new DateTimeImmutable()));

        self::assertSame($expected, $result->outcome);
        self::assertSame($channel, $result->channelName);
        self::assertSame('Target', $result->targetNickname);
        self::assertSame('', $result->detail);
        self::assertSame([], $network->calls);
        self::assertSame([], $audit->records);
    }

    #[Test]
    public function refusesIdentifiedIrcOperatorWithAssignedRole(): void
    {
        $network = new RecordingChannelOverrideActions();
        $audit = new RecordingCommandAuditRecorder();

        $result = $this->handler($network, $audit, member: true, operatorWithRole: true)
            ->handle(new KickChannelMember('Oper', 'OperServ', '#help', 'Target', 'reason', new DateTimeImmutable()));

        self::assertSame(ChannelOverrideOutcome::ProtectedIrcOperator, $result->outcome);
        self::assertSame([], $network->calls);
        self::assertSame([], $audit->records);
    }

    #[Test]
    public function reportsUnavailableNetworkWithoutAudit(): void
    {
        $network = new RecordingChannelOverrideActions(false);
        $audit = new RecordingCommandAuditRecorder();

        $result = $this->handler($network, $audit, member: true)
            ->handle(new KickChannelMember('Oper', 'OperServ', '#help', 'Target', 'reason', new DateTimeImmutable()));

        self::assertSame(ChannelOverrideOutcome::NetworkUnavailable, $result->outcome);
        self::assertCount(1, $network->calls);
        self::assertSame([], $audit->records);
    }

    /** @param list<string> $roots */
    private function handler(
        RecordingChannelOverrideActions $network,
        RecordingCommandAuditRecorder $audit,
        bool $channelExists = true,
        bool $online = true,
        bool $member = false,
        array $roots = [],
        bool $operatorWithRole = false,
    ): KickChannelMemberHandler {
        $channels = $this->createStub(NetworkChannelLookup::class);
        $channels->method('findByName')->willReturn($channelExists ? new NetworkChannel('#help', $member ? ['UID1'] : ['UID9']) : null);
        $users = $this->createStub(NetworkUserLookup::class);
        $users->method('findByNickname')->willReturn($online ? new NetworkUser('UID1', 'Target', 'ident', 'host.test', 'encoded-ip', $operatorWithRole, $operatorWithRole) : null);
        $rootRegistry = $this->createStub(RootIdentityRegistry::class);
        $rootRegistry->method('contains')->willReturnCallback(static fn (string $nickname): bool => in_array(strtolower($nickname), $roots, true));
        $accounts = $this->createStub(OperatorAccountLookup::class);
        $accounts->method('findIdByNickname')->willReturn(7);
        $roles = $this->createStub(OperatorRoleAccess::class);
        $roles->method('hasAssignedRole')->willReturn($operatorWithRole);

        return new KickChannelMemberHandler($channels, $users, new ProtectedNetworkTargets($rootRegistry, $accounts, $roles), $network, $audit);
    }
}
