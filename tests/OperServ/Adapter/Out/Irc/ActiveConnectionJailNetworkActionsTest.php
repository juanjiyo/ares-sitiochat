<?php

declare(strict_types=1);

namespace App\Tests\OperServ\Adapter\Out\Irc;

use App\Irc\Application\Port\In\ActiveProtocolModuleHolderInterface;
use App\Irc\Application\Port\In\ChannelLookupPort;
use App\Irc\Application\Port\In\ChannelView;
use App\Irc\Application\Port\In\ProtocolModuleInterface;
use App\Irc\Application\Port\In\ProtocolServiceActionsInterface;
use App\OperServ\Adapter\Out\Irc\ActiveConnectionJailNetworkActions;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(ActiveConnectionJailNetworkActions::class)]
final class ActiveConnectionJailNetworkActionsTest extends TestCase
{
    #[Test]
    public function listsTheChannelsOfAUser(): void
    {
        $channels = $this->createStub(ChannelLookupPort::class);
        $channels->method('listAll')->willReturn([
            new ChannelView('#a', '+nt', null, 2, [['uid' => 'U2', 'roleLetter' => ''], ['uid' => 'U1', 'roleLetter' => 'o']]),
            new ChannelView('#b', '+nt', null, 1, [['uid' => 'U2', 'roleLetter' => '']]),
            new ChannelView('#c', '+nt', null, 1, [['uid' => 'U1', 'roleLetter' => '']]),
        ]);

        self::assertSame(['#a', '#c'], new ActiveConnectionJailNetworkActions($this->createStub(ActiveProtocolModuleHolderInterface::class), $channels)->channelsOf('U1'));
    }

    #[Test]
    public function partsAndJoinsThroughTheActiveProtocol(): void
    {
        $actions = $this->createMock(ProtocolServiceActionsInterface::class);
        $actions->expects(self::once())->method('forcePart')->with('001', 'U1', '#a');
        $actions->expects(self::once())->method('forceJoin')->with('001', 'U1', '#Sumidero');
        $module = $this->createStub(ProtocolModuleInterface::class);
        $module->method('getServiceActions')->willReturn($actions);
        $connection = $this->createStub(ActiveProtocolModuleHolderInterface::class);
        $connection->method('getProtocolModule')->willReturn($module);
        $connection->method('getServerSid')->willReturn('001');
        $jail = new ActiveConnectionJailNetworkActions($connection, $this->createStub(ChannelLookupPort::class));

        self::assertTrue($jail->part('U1', '#a'));
        self::assertTrue($jail->join('U1', '#Sumidero'));
    }

    #[Test]
    public function refusesWithoutAnActiveProtocol(): void
    {
        $connection = $this->createStub(ActiveProtocolModuleHolderInterface::class);
        $connection->method('getProtocolModule')->willReturn(null);
        $jail = new ActiveConnectionJailNetworkActions($connection, $this->createStub(ChannelLookupPort::class));

        self::assertFalse($jail->part('U1', '#a'));
        self::assertFalse($jail->join('U1', '#Sumidero'));
    }
}
