<?php

declare(strict_types=1);

namespace App\Tests\GlobalServ\Adapter\Out\Irc;

use App\GlobalServ\Adapter\Out\Irc\ActiveConnectionChannelMemberNotice;
use App\GlobalServ\Adapter\Out\Irc\ActiveConnectionMassBroadcast;
use App\GlobalServ\Adapter\Out\Irc\ActiveConnectionRankFilteredBroadcast;
use App\GlobalServ\Application\Port\Out\GlobalServNetworkIdentity;
use App\Irc\Application\Port\In\ActiveConnectionHolderInterface;
use App\Irc\Application\Port\In\ChannelLookupPort;
use App\Irc\Application\Port\In\ChannelView;
use App\Irc\Application\Port\In\NetworkUserLookupPort;
use App\Irc\Application\Port\In\SendNoticePort;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(ActiveConnectionMassBroadcast::class)]
#[CoversClass(ActiveConnectionChannelMemberNotice::class)]
#[CoversClass(ActiveConnectionRankFilteredBroadcast::class)]
final class ActiveConnectionBroadcastsTest extends TestCase
{
    #[Test]
    public function massBroadcastNotifiesEveryConnectedUser(): void
    {
        $sent = [];
        $port = $this->createMock(SendNoticePort::class);
        $port->expects(self::exactly(2))->method('sendNotice')->willReturnCallback(
            static function (string $from, string $to, string $message) use (&$sent): void {
                $sent[] = [$from, $to, $message];
            },
        );

        $broadcast = new ActiveConnectionMassBroadcast(
            $this->holder(connected: true),
            $this->identity('003Z00001'),
            $this->users(['001A', '001B']),
            $port,
        );

        self::assertSame(2, $broadcast->broadcastToAll('hi'));
        self::assertSame([['003Z00001', '001A', 'hi'], ['003Z00001', '001B', 'hi']], $sent);
    }

    #[Test]
    public function massBroadcastReportsAnUnavailableNetwork(): void
    {
        $port = $this->createMock(SendNoticePort::class);
        $port->expects(self::never())->method('sendNotice');

        $offline = new ActiveConnectionMassBroadcast($this->holder(connected: false), $this->identity('003Z00001'), $this->users(['001A']), $port);
        self::assertNull($offline->broadcastToAll('hi'));

        $withoutUid = new ActiveConnectionMassBroadcast($this->holder(connected: true), $this->identity(null), $this->users(['001A']), $port);
        self::assertNull($withoutUid->broadcastToAll('hi'));
    }

    #[Test]
    public function channelNoticeReachesEveryMember(): void
    {
        $sent = [];
        $port = $this->createMock(SendNoticePort::class);
        $port->expects(self::exactly(2))->method('sendNotice')->willReturnCallback(
            static function (string $from, string $to, string $message) use (&$sent): void {
                $sent[] = $to;
            },
        );

        $channels = $this->createStub(ChannelLookupPort::class);
        $channels->method('findByChannelName')->willReturn(
            new ChannelView('#room', '+nt', null, 2, [['uid' => '001A', 'roleLetter' => ''], ['uid' => '001B', 'roleLetter' => '']]),
        );

        $notice = new ActiveConnectionChannelMemberNotice($this->holder(connected: true), $channels, $this->identity('003Z00001'), $port);

        self::assertSame(2, $notice->sendToChannelMembers('#room', 'hello'));
        self::assertSame(['001A', '001B'], $sent);
    }

    #[Test]
    public function channelNoticeReportsAnUnavailableNetworkOrUnknownChannel(): void
    {
        $port = $this->createMock(SendNoticePort::class);
        $port->expects(self::never())->method('sendNotice');

        $channels = $this->createStub(ChannelLookupPort::class);
        $channels->method('findByChannelName')->willReturn(null);

        self::assertNull(new ActiveConnectionChannelMemberNotice($this->holder(false), $channels, $this->identity('003Z00001'), $port)->sendToChannelMembers('#room', 'x'));
        self::assertNull(new ActiveConnectionChannelMemberNotice($this->holder(true), $channels, $this->identity(null), $port)->sendToChannelMembers('#room', 'x'));
        self::assertNull(new ActiveConnectionChannelMemberNotice($this->holder(true), $channels, $this->identity('003Z00001'), $port)->sendToChannelMembers('#room', 'x'));
    }

    #[Test]
    public function rankFilteredBroadcastReachesTheGivenUids(): void
    {
        $sent = [];
        $port = $this->createMock(SendNoticePort::class);
        $port->expects(self::exactly(2))->method('sendNotice')->willReturnCallback(
            static function (string $from, string $to, string $message) use (&$sent): void {
                $sent[] = $to;
            },
        );

        $broadcast = new ActiveConnectionRankFilteredBroadcast($this->holder(true), $this->identity('003Z00001'), $port);

        self::assertSame(2, $broadcast->broadcastToUids(['001A', '001B'], 'x'));
        self::assertSame(['001A', '001B'], $sent);
    }

    #[Test]
    public function rankFilteredBroadcastReportsAnUnavailableNetwork(): void
    {
        $port = $this->createMock(SendNoticePort::class);
        $port->expects(self::never())->method('sendNotice');

        self::assertNull(new ActiveConnectionRankFilteredBroadcast($this->holder(false), $this->identity('003Z00001'), $port)->broadcastToUids(['001A'], 'x'));
        self::assertNull(new ActiveConnectionRankFilteredBroadcast($this->holder(true), $this->identity(null), $port)->broadcastToUids(['001A'], 'x'));
    }

    private function holder(bool $connected): ActiveConnectionHolderInterface
    {
        $holder = $this->createStub(ActiveConnectionHolderInterface::class);
        $holder->method('isConnected')->willReturn($connected);

        return $holder;
    }

    private function identity(?string $uid): GlobalServNetworkIdentity
    {
        $identity = $this->createStub(GlobalServNetworkIdentity::class);
        $identity->method('serviceUid')->willReturn($uid);

        return $identity;
    }

    /**
     * @param list<string> $uids
     */
    private function users(array $uids): NetworkUserLookupPort
    {
        $users = $this->createStub(NetworkUserLookupPort::class);
        $users->method('listConnectedUids')->willReturn($uids);

        return $users;
    }
}
