<?php

declare(strict_types=1);

namespace App\Tests\GlobalServ\Application\UseCase;

use App\GlobalServ\Application\Model\BroadcastOutcome;
use App\GlobalServ\Application\Model\BroadcastResult;
use App\GlobalServ\Application\Model\MassChannelJoinResult;
use App\GlobalServ\Application\Port\Out\ChannelMemberNoticePort;
use App\GlobalServ\Application\Port\Out\MassBroadcastPort;
use App\GlobalServ\Application\Port\Out\MassChannelJoinPort;
use App\GlobalServ\Application\Port\Out\OperatorRankLookupPort;
use App\GlobalServ\Application\Port\Out\PseudoClientBroadcastPort;
use App\GlobalServ\Application\Port\Out\RankFilteredBroadcastPort;
use App\GlobalServ\Application\UseCase\MassJoin\MassJoinChannel;
use App\GlobalServ\Application\UseCase\MassJoin\MassJoinChannelHandler;
use App\GlobalServ\Application\UseCase\SendChannel\SendChannelMessage;
use App\GlobalServ\Application\UseCase\SendChannel\SendChannelMessageHandler;
use App\GlobalServ\Application\UseCase\SendGlobal\SendGlobalMessage;
use App\GlobalServ\Application\UseCase\SendGlobal\SendGlobalMessageHandler;
use App\GlobalServ\Application\UseCase\SendNick\SendNickBroadcast;
use App\GlobalServ\Application\UseCase\SendNick\SendNickBroadcastHandler;
use App\GlobalServ\Application\UseCase\SendRank\SendRankBroadcast;
use App\GlobalServ\Application\UseCase\SendRank\SendRankBroadcastHandler;
use App\GlobalServ\Domain\ValueObject\GlobalServRank;
use App\Irc\Application\Port\In\ChannelLookupPort;
use App\Irc\Application\Port\In\ChannelView;
use App\Irc\Application\Port\In\NetworkUserLookupPort;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(SendNickBroadcastHandler::class)]
#[CoversClass(SendChannelMessageHandler::class)]
#[CoversClass(SendRankBroadcastHandler::class)]
#[CoversClass(MassJoinChannelHandler::class)]
#[CoversClass(SendGlobalMessageHandler::class)]
#[CoversClass(BroadcastResult::class)]
#[CoversClass(MassChannelJoinResult::class)]
#[CoversClass(SendNickBroadcast::class)]
#[CoversClass(SendChannelMessage::class)]
#[CoversClass(SendRankBroadcast::class)]
#[CoversClass(MassJoinChannel::class)]
#[CoversClass(SendGlobalMessage::class)]
final class GlobalServHandlersTest extends TestCase
{
    #[Test]
    public function nickBroadcastReportsSentAndUnavailableOutcomes(): void
    {
        $port = $this->createStub(PseudoClientBroadcastPort::class);
        $port->method('broadcastFromPseudoClient')->willReturn(4, null);

        $handler = new SendNickBroadcastHandler($port);
        $command = new SendNickBroadcast('Alice', 'Notice', 'hi');

        $sent = $handler->handle($command);
        self::assertSame(BroadcastOutcome::Sent, $sent->outcome);
        self::assertSame(4, $sent->recipientCount);
        self::assertSame(BroadcastOutcome::NetworkUnavailable, $handler->handle($command)->outcome);
    }

    #[Test]
    public function channelMessageReportsNotFoundSentAndUnavailableOutcomes(): void
    {
        $channels = $this->createStub(ChannelLookupPort::class);
        $channels->method('findByChannelName')->willReturn(null, new ChannelView('#room', '+nt', null, 0), new ChannelView('#room', '+nt', null, 0));
        $port = $this->createStub(ChannelMemberNoticePort::class);
        $port->method('sendToChannelMembers')->willReturn(3, null);

        $handler = new SendChannelMessageHandler($channels, $port);
        $command = new SendChannelMessage('Alice', '#room', 'hi');

        $notFound = $handler->handle($command);
        self::assertSame(BroadcastOutcome::ChannelNotFound, $notFound->outcome);
        self::assertSame('#room', $notFound->channelName);
        self::assertSame(BroadcastOutcome::Sent, $handler->handle($command)->outcome);
        self::assertSame(BroadcastOutcome::NetworkUnavailable, $handler->handle($command)->outcome);
    }

    #[Test]
    public function rankBroadcastSplitsUsersByRankAndHandlesUnavailableOutcomes(): void
    {
        $users = $this->createStub(NetworkUserLookupPort::class);
        $users->method('listConnectedUids')->willReturn(['001A', '001B']);

        $ranks = $this->createStub(OperatorRankLookupPort::class);
        $ranks->method('hasAtLeastRank')->willReturnCallback(
            static fn (string $uid): bool => '001A' === $uid,
        );

        $port = $this->createStub(RankFilteredBroadcastPort::class);
        $port->method('broadcastToUids')->willReturn(1, null);

        $handler = new SendRankBroadcastHandler($users, $ranks, $port);
        $command = new SendRankBroadcast('Alice', GlobalServRank::OPER, 'hi');

        self::assertSame(BroadcastOutcome::Sent, $handler->handle($command)->outcome);
        self::assertSame(BroadcastOutcome::NetworkUnavailable, $handler->handle($command)->outcome);
    }

    #[Test]
    public function rankBroadcastReportsAnUndeterminableRank(): void
    {
        $users = $this->createStub(NetworkUserLookupPort::class);
        $users->method('listConnectedUids')->willReturn(['001A']);

        $ranks = $this->createStub(OperatorRankLookupPort::class);
        $ranks->method('hasAtLeastRank')->willReturn(null);

        $handler = new SendRankBroadcastHandler($users, $ranks, $this->createStub(RankFilteredBroadcastPort::class));

        self::assertSame(BroadcastOutcome::RankUnsupported, $handler->handle(new SendRankBroadcast('Alice', GlobalServRank::ADMIN, 'hi'))->outcome);
    }

    #[Test]
    public function massJoinReportsUnsupportedSentAndUnavailableOutcomes(): void
    {
        $join = $this->createStub(MassChannelJoinPort::class);
        $join->method('joinAllUsersToChannel')->willReturn(
            MassChannelJoinResult::unsupported(),
            MassChannelJoinResult::supported(2),
            MassChannelJoinResult::supported(2),
        );

        $broadcast = $this->createStub(MassBroadcastPort::class);
        $broadcast->method('broadcastToAll')->willReturn(2, null);

        $handler = new MassJoinChannelHandler($join, $broadcast);
        $command = new MassJoinChannel('Alice', '#room', 'reason');

        self::assertSame(BroadcastOutcome::JoinUnsupported, $handler->handle($command)->outcome);
        self::assertSame(BroadcastOutcome::Sent, $handler->handle($command)->outcome);
        self::assertSame(BroadcastOutcome::NetworkUnavailable, $handler->handle($command)->outcome);
    }

    #[Test]
    public function sendGlobalMessageBroadcastsWithoutAnIdentityCheck(): void
    {
        $port = $this->createStub(MassBroadcastPort::class);
        $port->method('broadcastToAll')->willReturn(7, null);

        $handler = new SendGlobalMessageHandler($port);
        $command = new SendGlobalMessage('Alice', 'hi');

        $sent = $handler->handle($command);
        self::assertSame(BroadcastOutcome::Sent, $sent->outcome);
        self::assertSame(7, $sent->recipientCount);
        self::assertSame(BroadcastOutcome::NetworkUnavailable, $handler->handle($command)->outcome);
    }
}
