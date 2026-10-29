<?php

declare(strict_types=1);

namespace App\Tests\OperServ\Application\UseCase\ChannelOverride;

use App\OperServ\Application\Port\In\Audit\CommandAuditCategory;
use App\OperServ\Application\Port\Out\NetworkChannel;
use App\OperServ\Application\Port\Out\NetworkChannelLookup;
use App\OperServ\Application\Security\OperServPermission;
use App\OperServ\Application\UseCase\ChannelOverride\ChannelOverrideOutcome;
use App\OperServ\Application\UseCase\ChannelOverride\ChannelOverrideResult;
use App\OperServ\Application\UseCase\ChannelOverride\InviteOperator;
use App\OperServ\Application\UseCase\ChannelOverride\InviteOperatorHandler;
use DateTimeImmutable;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(InviteOperator::class)]
#[CoversClass(InviteOperatorHandler::class)]
#[CoversClass(ChannelOverrideResult::class)]
#[CoversClass(NetworkChannel::class)]
final class InviteOperatorHandlerTest extends TestCase
{
    #[Test]
    public function invitesCallerAndRecordsAudit(): void
    {
        $network = new RecordingChannelOverrideActions();
        $audit = new RecordingCommandAuditRecorder();
        $at = new DateTimeImmutable('2026-10-29T10:00:00+00:00');

        $result = $this->handler($network, $audit)->handle(new InviteOperator('Oper', 'UIDOPER', '#Help', $at));

        self::assertSame(ChannelOverrideOutcome::Applied, $result->outcome);
        self::assertSame('#help', $result->channelName);
        self::assertSame('Oper', $result->targetNickname);
        self::assertSame([['invite', '#help', ['UIDOPER']]], $network->calls);
        self::assertCount(1, $audit->records);
        $record = $audit->records[0];
        self::assertSame(CommandAuditCategory::OperatorAction, $record->category);
        self::assertSame('OperServ', $record->service);
        self::assertSame('Oper', $record->actor);
        self::assertSame('INVITE', $record->operation);
        self::assertSame($at, $record->occurredAt);
        self::assertSame('#help', $record->target);
        self::assertNull($record->reason);
        self::assertSame(OperServPermission::INVITE, $record->permission);
        self::assertSame(['channel' => '#help'], $record->metadata);
    }

    /** @return iterable<string, array{string, bool, bool, ChannelOverrideOutcome}> */
    public static function refusals(): iterable
    {
        yield 'channel without prefix' => ['help', true, false, ChannelOverrideOutcome::InvalidChannel];
        yield 'channel not on network' => ['#help', false, false, ChannelOverrideOutcome::ChannelNotFound];
        yield 'caller already inside' => ['#help', true, true, ChannelOverrideOutcome::AlreadyOnChannel];
    }

    #[Test]
    #[DataProvider('refusals')]
    public function refusesWithoutNetworkEffectOrAudit(string $channel, bool $exists, bool $inside, ChannelOverrideOutcome $expected): void
    {
        $network = new RecordingChannelOverrideActions();
        $audit = new RecordingCommandAuditRecorder();

        $result = $this->handler($network, $audit, $exists, $inside)->handle(new InviteOperator('Oper', 'UIDOPER', $channel, new DateTimeImmutable()));

        self::assertSame($expected, $result->outcome);
        self::assertSame($channel, $result->channelName);
        self::assertSame('Oper', $result->targetNickname);
        self::assertSame([], $network->calls);
        self::assertSame([], $audit->records);
    }

    #[Test]
    public function reportsUnavailableNetworkWithoutAudit(): void
    {
        $network = new RecordingChannelOverrideActions(false);
        $audit = new RecordingCommandAuditRecorder();

        $result = $this->handler($network, $audit)->handle(new InviteOperator('Oper', 'UIDOPER', '#help', new DateTimeImmutable()));

        self::assertSame(ChannelOverrideOutcome::NetworkUnavailable, $result->outcome);
        self::assertCount(1, $network->calls);
        self::assertSame([], $audit->records);
    }

    private function handler(RecordingChannelOverrideActions $network, RecordingCommandAuditRecorder $audit, bool $channelExists = true, bool $inside = false): InviteOperatorHandler
    {
        $channels = $this->createStub(NetworkChannelLookup::class);
        $channels->method('findByName')->willReturn($channelExists ? new NetworkChannel('#help', $inside ? ['UIDOPER'] : ['UID2']) : null);

        return new InviteOperatorHandler($channels, $network, $audit);
    }
}
