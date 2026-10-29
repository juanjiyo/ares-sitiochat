<?php

declare(strict_types=1);

namespace App\Tests\OperServ\Application\UseCase\ChannelOverride;

use App\OperServ\Application\Port\In\Audit\CommandAuditCategory;
use App\OperServ\Application\Port\Out\NetworkChannel;
use App\OperServ\Application\Port\Out\NetworkChannelLookup;
use App\OperServ\Application\Security\OperServPermission;
use App\OperServ\Application\UseCase\ChannelOverride\ChannelOverrideOutcome;
use App\OperServ\Application\UseCase\ChannelOverride\ChannelOverrideResult;
use App\OperServ\Application\UseCase\ChannelOverride\SetChannelModes;
use App\OperServ\Application\UseCase\ChannelOverride\SetChannelModesHandler;
use DateTimeImmutable;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(SetChannelModes::class)]
#[CoversClass(SetChannelModesHandler::class)]
#[CoversClass(ChannelOverrideResult::class)]
#[CoversClass(NetworkChannel::class)]
final class SetChannelModesHandlerTest extends TestCase
{
    #[Test]
    public function appliesCustomModesWithParametersAndRecordsAudit(): void
    {
        $network = new RecordingChannelOverrideActions();
        $audit = new RecordingCommandAuditRecorder();
        $at = new DateTimeImmutable('2026-10-29T10:00:00+00:00');

        $result = $this->handler($network, $audit)->handle(SetChannelModes::custom('Oper', '#Help', '+kl', ['secret', '10'], $at));

        self::assertSame(ChannelOverrideOutcome::Applied, $result->outcome);
        self::assertSame('#help', $result->channelName);
        self::assertSame('', $result->targetNickname);
        self::assertSame('+kl secret 10', $result->detail);
        self::assertSame([['modes', '#help', ['+kl', 'secret', '10']]], $network->calls);
        self::assertCount(1, $audit->records);
        $record = $audit->records[0];
        self::assertSame(CommandAuditCategory::OperatorAction, $record->category);
        self::assertSame('OperServ', $record->service);
        self::assertSame('Oper', $record->actor);
        self::assertSame('MODE', $record->operation);
        self::assertSame($at, $record->occurredAt);
        self::assertSame('#help', $record->target);
        self::assertNull($record->reason);
        self::assertSame(OperServPermission::MODE, $record->permission);
        self::assertNull($record->targetHost);
        self::assertNull($record->targetIp);
        self::assertSame(['channel' => '#help', 'modes' => '+kl secret 10'], $record->metadata);
    }

    #[Test]
    public function cleanupRemovesRestrictiveModesAndRecordsAudit(): void
    {
        $network = new RecordingChannelOverrideActions();
        $audit = new RecordingCommandAuditRecorder();

        $command = SetChannelModes::cleanup('Oper', '#help', new DateTimeImmutable());
        $result = $this->handler($network, $audit)->handle($command);

        self::assertTrue($command->cleanup);
        self::assertSame([], $command->params);
        self::assertSame(ChannelOverrideOutcome::Applied, $result->outcome);
        self::assertSame('-imnpst', $result->detail);
        self::assertSame([['modes', '#help', ['-imnpst']]], $network->calls);
        self::assertSame('LIMPIA', $audit->records[0]->operation);
        self::assertSame(OperServPermission::MODE, $audit->records[0]->permission);
        self::assertSame(['channel' => '#help', 'modes' => '-imnpst'], $audit->records[0]->metadata);
    }

    /** @return iterable<string, array{string, string, bool, ChannelOverrideOutcome, string}> */
    public static function refusals(): iterable
    {
        yield 'channel without prefix' => ['help', '+m', true, ChannelOverrideOutcome::InvalidChannel, ''];
        yield 'channel not on network' => ['#help', '+m', false, ChannelOverrideOutcome::ChannelNotFound, ''];
        yield 'modes without sign' => ['#help', 'm', true, ChannelOverrideOutcome::InvalidModes, 'm'];
        yield 'sign without letter' => ['#help', '+-', true, ChannelOverrideOutcome::InvalidModes, '+-'];
        yield 'invalid characters' => ['#help', '+m#', true, ChannelOverrideOutcome::InvalidModes, '+m#'];
    }

    #[Test]
    #[DataProvider('refusals')]
    public function refusesWithoutNetworkEffectOrAudit(string $channel, string $modes, bool $exists, ChannelOverrideOutcome $expected, string $detail): void
    {
        $network = new RecordingChannelOverrideActions();
        $audit = new RecordingCommandAuditRecorder();

        $result = $this->handler($network, $audit, $exists)->handle(SetChannelModes::custom('Oper', $channel, $modes, [], new DateTimeImmutable()));

        self::assertSame($expected, $result->outcome);
        self::assertSame($channel, $result->channelName);
        self::assertSame($detail, $result->detail);
        self::assertSame([], $network->calls);
        self::assertSame([], $audit->records);
    }

    #[Test]
    public function reportsUnavailableNetworkWithoutAudit(): void
    {
        $network = new RecordingChannelOverrideActions(false);
        $audit = new RecordingCommandAuditRecorder();

        $result = $this->handler($network, $audit)->handle(SetChannelModes::cleanup('Oper', '#help', new DateTimeImmutable()));

        self::assertSame(ChannelOverrideOutcome::NetworkUnavailable, $result->outcome);
        self::assertCount(1, $network->calls);
        self::assertSame([], $audit->records);
    }

    private function handler(RecordingChannelOverrideActions $network, RecordingCommandAuditRecorder $audit, bool $channelExists = true): SetChannelModesHandler
    {
        $channels = $this->createStub(NetworkChannelLookup::class);
        $channels->method('findByName')->willReturn($channelExists ? new NetworkChannel('#help', []) : null);

        return new SetChannelModesHandler($channels, $network, $audit);
    }
}
