<?php

declare(strict_types=1);

namespace App\Tests\OperServ\Adapter\Out\Irc;

use App\Irc\Application\Port\In\ChannelLookupPort;
use App\Irc\Application\Port\In\ChannelView;
use App\OperServ\Adapter\Out\Irc\IrcNetworkChannelLookup;
use App\OperServ\Application\Port\Out\NetworkChannel;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(IrcNetworkChannelLookup::class)]
#[CoversClass(NetworkChannel::class)]
final class IrcNetworkChannelLookupTest extends TestCase
{
    #[Test]
    public function projectsChannelNameAndMemberUids(): void
    {
        $channels = $this->createMock(ChannelLookupPort::class);
        $channels->expects(self::once())->method('findByChannelName')->with('#Help')->willReturn(new ChannelView(
            '#help',
            '+nt',
            null,
            2,
            [['uid' => 'UID1', 'roleLetter' => 'o'], ['uid' => 'UID2', 'roleLetter' => '']],
        ));

        $channel = new IrcNetworkChannelLookup($channels)->findByName('#Help');

        self::assertNotNull($channel);
        self::assertSame('#help', $channel->name);
        self::assertSame(['UID1', 'UID2'], $channel->memberUids);
        self::assertTrue($channel->hasMember('UID2'));
        self::assertFalse($channel->hasMember('UID3'));
    }

    #[Test]
    public function returnsNullForUnknownChannel(): void
    {
        $channels = $this->createStub(ChannelLookupPort::class);
        $channels->method('findByChannelName')->willReturn(null);

        self::assertNull(new IrcNetworkChannelLookup($channels)->findByName('#missing'));
    }
}
