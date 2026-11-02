<?php

declare(strict_types=1);

namespace App\Tests\Irc\Adapter\In\Event;

use App\ChanServ\Application\Port\In\RegisteredChannelSetup;
use App\Irc\Adapter\Event\NetworkBurstCompleteEvent;
use App\Irc\Adapter\Event\NetworkSyncCompleteEvent;
use App\Irc\Adapter\In\Event\DebugChannelJoinSubscriber;
use App\Irc\Adapter\Out\Connection\ActiveConnectionHolder;
use App\Irc\Adapter\Out\Connection\ConnectionInterface;
use App\Irc\Application\Port\In\ChannelLookupPort;
use App\Irc\Application\Port\In\ChannelModeSupportInterface;
use App\Irc\Application\Port\In\ChannelView;
use App\Irc\Application\Port\In\ProtocolModuleInterface;
use App\Irc\Application\Port\In\ProtocolServiceActionsInterface;
use App\Irc\Application\Port\In\ServiceChannelRegistrationPort;
use App\Irc\Application\Port\In\ServiceDebugNotifierInterface;
use App\Irc\Application\Port\In\ServiceUidProviderInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(DebugChannelJoinSubscriber::class)]
final class DebugChannelJoinSubscriberTest extends TestCase
{
    #[Test]
    public function subscribesToBurstAndNetworkSynchronization(): void
    {
        self::assertSame([
            NetworkBurstCompleteEvent::class => ['onBurstComplete', 0],
            NetworkSyncCompleteEvent::class => ['onSyncComplete', -30],
        ], DebugChannelJoinSubscriber::getSubscribedEvents());
    }

    #[Test]
    public function asksEveryNotifierToJoinAfterBurst(): void
    {
        $first = $this->createMock(ServiceDebugNotifierInterface::class);
        $first->expects(self::once())->method('ensureChannelJoined');
        $second = $this->createMock(ServiceDebugNotifierInterface::class);
        $second->expects(self::once())->method('ensureChannelJoined');

        $subscriber = new DebugChannelJoinSubscriber(
            [$first, $second],
            null,
            $this->createStub(RegisteredChannelSetup::class),
        );

        $subscriber->onBurstComplete(new NetworkBurstCompleteEvent(
            $this->createStub(ConnectionInterface::class),
            '001',
        ));
    }

    #[Test]
    public function acceptsAnEmptyNotifierCollection(): void
    {
        self::expectNotToPerformAssertions();

        $subscriber = new DebugChannelJoinSubscriber(
            [],
            null,
            $this->createStub(RegisteredChannelSetup::class),
        );

        $subscriber->onBurstComplete(new NetworkBurstCompleteEvent(
            $this->createStub(ConnectionInterface::class),
            '001',
        ));
    }

    #[Test]
    public function ignoresMissingDebugChannelAfterNetworkSynchronization(): void
    {
        $setup = $this->createMock(RegisteredChannelSetup::class);
        $setup->expects(self::never())->method('restore');
        $event = new NetworkSyncCompleteEvent($this->createStub(ConnectionInterface::class), '001');

        new DebugChannelJoinSubscriber([], null, $setup)->onSyncComplete($event);
        new DebugChannelJoinSubscriber([], '', $setup)->onSyncComplete($event);
    }

    #[Test]
    public function delegatesConfiguredChannelSetupAfterNetworkSynchronization(): void
    {
        $setup = $this->createMock(RegisteredChannelSetup::class);
        $setup->expects(self::once())->method('restore')->with('#opers');
        $subscriber = new DebugChannelJoinSubscriber([], '#opers', $setup);

        $subscriber->onSyncComplete(new NetworkSyncCompleteEvent(
            $this->createStub(ConnectionInterface::class),
            '001',
        ));
    }

    /** @param list<string> $prefixes */
    private function module(ProtocolServiceActionsInterface $actions, array $prefixes): ProtocolModuleInterface
    {
        $modes = $this->createStub(ChannelModeSupportInterface::class);
        $modes->method('getSupportedPrefixModes')->willReturn($prefixes);
        $module = $this->createStub(ProtocolModuleInterface::class);
        $module->method('getServiceActions')->willReturn($actions);
        $module->method('getChannelModeSupport')->willReturn($modes);

        return $module;
    }

    private function bot(string $uid): ServiceUidProviderInterface
    {
        $bot = $this->createStub(ServiceUidProviderInterface::class);
        $bot->method('getUid')->willReturn($uid);

        return $bot;
    }

    #[Test]
    public function seatsEveryServiceBotWithOpAndTurnsHigherPrefixesIntoOp(): void
    {
        $joined = [];
        $actions = $this->createStub(ProtocolServiceActionsInterface::class);
        $actions->method('joinChannelAsService')->willReturnCallback(
            static function (string $sid, string $channel, string $uid, string $prefix, ?int $ts) use (&$joined): void {
                $joined[] = [$sid, $channel, $uid, $prefix, $ts];
            },
        );
        $memberModes = [];
        $actions->method('setChannelMemberMode')->willReturnCallback(
            static function (string $sid, string $channel, string $uid, string $letter, bool $add, string $source = '', ?int $ts = null) use (&$memberModes): void {
                $memberModes[] = [$channel, $uid, $letter, $add, $ts];
            },
        );
        $holder = new ActiveConnectionHolder();
        $holder->setProtocolModule($this->module($actions, ['q', 'o', 'v']));

        $channels = $this->createStub(ChannelLookupPort::class);
        $channels->method('findByChannelName')->willReturn(
            new ChannelView('#DeBuG', '+nt', null, 1, [['uid' => '001B00001', 'roleLetter' => 'q']], 1234),
        );
        $registration = $this->createMock(ServiceChannelRegistrationPort::class);
        $registration->expects(self::exactly(2))->method('registerServiceChannelJoin');

        $subscriber = new DebugChannelJoinSubscriber(
            [],
            '#DeBuG',
            $this->createStub(RegisteredChannelSetup::class),
            // CHaN is already in, a bot without UID is not introduced, NiCK twice joins once.
            [$this->bot('001A00001'), $this->bot('001B00001'), $this->bot(''), $this->bot('001Z00002'), $this->bot('001A00001')],
            $holder,
            $channels,
            $registration,
        );
        $subscriber->onSyncComplete(new NetworkSyncCompleteEvent($this->createStub(ConnectionInterface::class), '001'));

        self::assertSame([
            ['001', '#DeBuG', '001A00001', 'o', 1234],
            ['001', '#DeBuG', '001Z00002', 'o', 1234],
        ], $joined);
        self::assertSame([
            ['#DeBuG', '001B00001', 'q', false, 1234],
            ['#DeBuG', '001B00001', 'o', true, 1234],
        ], $memberModes);
    }

    #[Test]
    public function seatsBotsWithOpInANewChannel(): void
    {
        $actions = $this->createMock(ProtocolServiceActionsInterface::class);
        $actions->expects(self::once())->method('joinChannelAsService')
            ->with('001', '#DeBuG', '001A00001', 'o', self::greaterThan(0));
        $holder = new ActiveConnectionHolder();
        $holder->setProtocolModule($this->module($actions, []));

        new DebugChannelJoinSubscriber(
            [],
            '#DeBuG',
            $this->createStub(RegisteredChannelSetup::class),
            [$this->bot('001A00001')],
            $holder,
            $this->createStub(ChannelLookupPort::class),
            $this->createStub(ServiceChannelRegistrationPort::class),
        )->onSyncComplete(new NetworkSyncCompleteEvent($this->createStub(ConnectionInterface::class), '001'));
    }

    #[Test]
    public function seatsNobodyWithoutAnActiveProtocolModule(): void
    {
        $registration = $this->createMock(ServiceChannelRegistrationPort::class);
        $registration->expects(self::never())->method('registerServiceChannelJoin');

        new DebugChannelJoinSubscriber(
            [],
            '#DeBuG',
            $this->createStub(RegisteredChannelSetup::class),
            [$this->bot('001A00001')],
            new ActiveConnectionHolder(),
            $this->createStub(ChannelLookupPort::class),
            $registration,
        )->onSyncComplete(new NetworkSyncCompleteEvent($this->createStub(ConnectionInterface::class), '001'));
    }
}
