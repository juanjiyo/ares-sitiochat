<?php

declare(strict_types=1);

namespace App\Tests\GlobalServ\Adapter\Out\Irc;

use App\GlobalServ\Adapter\Out\Irc\ActiveConnectionPseudoClientBroadcast;
use App\GlobalServ\Adapter\Out\Irc\GlobalServPseudoClientUidGenerator;
use App\Irc\Application\Port\In\ActiveConnectionHolderInterface;
use App\Irc\Application\Port\In\ActiveProtocolModuleHolderInterface;
use App\Irc\Application\Port\In\NetworkUserLookupPort;
use App\Irc\Application\Port\In\ProtocolModuleInterface;
use App\Irc\Application\Port\In\ProtocolServiceActionsInterface;
use App\Irc\Application\Port\In\SendNoticePort;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(ActiveConnectionPseudoClientBroadcast::class)]
final class ActiveConnectionPseudoClientBroadcastTest extends TestCase
{
    #[Test]
    public function introducesThePseudoClientBroadcastsAndQuitsIt(): void
    {
        $actions = $this->createMock(ProtocolServiceActionsInterface::class);
        $actions->expects(self::once())->method('introducePseudoClient')
            ->with('003', 'Notice', 'Notice', 'bots.example', '003Y00001', 'GlobalServ Notice');
        $actions->expects(self::once())->method('quitPseudoClient')->with('003', '003Y00001', 'GlobalServ');

        $sent = [];
        $messages = $this->createMock(SendNoticePort::class);
        $messages->expects(self::exactly(2))->method('sendNotice')->willReturnCallback(
            static function (string $from, string $to, string $message) use (&$sent): void {
                $sent[] = [$from, $to];
            },
        );

        $broadcast = new ActiveConnectionPseudoClientBroadcast(
            $this->connection($actions, connected: true),
            $this->generator(),
            $this->users(['001A', '001B']),
            $messages,
            'bots.example',
        );

        self::assertSame(2, $broadcast->broadcastFromPseudoClient('Notice', 'hi'));
        self::assertSame([['003Y00001', '001A'], ['003Y00001', '001B']], $sent);
    }

    #[Test]
    public function doesNothingWhenTheProtocolModuleIsMissing(): void
    {
        $connection = $this->createStub(ActiveProtocolModuleHolderInterface::class);
        $connection->method('getProtocolModule')->willReturn(null);

        $broadcast = new ActiveConnectionPseudoClientBroadcast(
            $connection,
            $this->generator(),
            $this->users(['001A']),
            $this->createStub(SendNoticePort::class),
            'bots.example',
        );

        self::assertNull($broadcast->broadcastFromPseudoClient('Notice', 'hi'));
    }

    #[Test]
    public function doesNothingWhenTheServerSidIsMissing(): void
    {
        $connection = $this->createStub(ActiveProtocolModuleHolderInterface::class);
        $connection->method('getProtocolModule')->willReturn($this->createStub(ProtocolModuleInterface::class));
        $connection->method('getServerSid')->willReturn(null);

        $broadcast = new ActiveConnectionPseudoClientBroadcast(
            $connection,
            $this->generator(),
            $this->users(['001A']),
            $this->createStub(SendNoticePort::class),
            'bots.example',
        );

        self::assertNull($broadcast->broadcastFromPseudoClient('Notice', 'hi'));
    }

    #[Test]
    public function doesNothingWhenTheConnectionIsOffline(): void
    {
        $actions = $this->createMock(ProtocolServiceActionsInterface::class);
        $actions->expects(self::never())->method('introducePseudoClient');

        $broadcast = new ActiveConnectionPseudoClientBroadcast(
            $this->connection($actions, connected: false),
            $this->generator(),
            $this->users(['001A']),
            $this->createStub(SendNoticePort::class),
            'bots.example',
        );

        self::assertNull($broadcast->broadcastFromPseudoClient('Notice', 'hi'));
    }

    #[Test]
    public function doesNothingWhenTheUidGeneratorCannotBuildAUid(): void
    {
        $actions = $this->createMock(ProtocolServiceActionsInterface::class);
        $actions->expects(self::never())->method('introducePseudoClient');

        $generator = new GlobalServPseudoClientUidGenerator($this->createStub(ActiveConnectionHolderInterface::class));

        $broadcast = new ActiveConnectionPseudoClientBroadcast(
            $this->connection($actions, connected: true),
            $generator,
            $this->users(['001A']),
            $this->createStub(SendNoticePort::class),
            'bots.example',
        );

        self::assertNull($broadcast->broadcastFromPseudoClient('Notice', 'hi'));
    }

    private function connection(ProtocolServiceActionsInterface $actions, bool $connected): ActiveProtocolModuleHolderInterface
    {
        $module = $this->createStub(ProtocolModuleInterface::class);
        $module->method('getServiceActions')->willReturn($actions);

        $connection = $this->createStub(ActiveProtocolModuleHolderInterface::class);
        $connection->method('getProtocolModule')->willReturn($module);
        $connection->method('getServerSid')->willReturn('003');
        $connection->method('isConnected')->willReturn($connected);

        return $connection;
    }

    private function generator(): GlobalServPseudoClientUidGenerator
    {
        $holder = $this->createStub(ActiveConnectionHolderInterface::class);
        $holder->method('getServerSid')->willReturn('003');

        return new GlobalServPseudoClientUidGenerator($holder);
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
