<?php

declare(strict_types=1);

namespace App\Tests\DebugServ\Adapter\In\Irc\Bot;

use App\DebugServ\Adapter\In\Irc\Bot\DebugServBot;
use App\Irc\Adapter\Event\NetworkSyncCompleteEvent;
use App\Irc\Adapter\Out\Connection\ActiveConnectionHolder;
use App\Irc\Adapter\Out\Connection\ConnectionInterface;
use App\Irc\Application\Port\In\ChannelLookupPort;
use App\Irc\Application\Port\In\ChannelModeSupportInterface;
use App\Irc\Application\Port\In\ChannelView;
use App\Irc\Application\Port\In\ProtocolModuleInterface;
use App\Irc\Application\Port\In\ProtocolServiceActionsInterface;
use App\Irc\Application\Port\In\SendNoticePort;
use App\Irc\Application\Port\In\ServiceChannelRegistrationPort;
use App\Irc\Application\Port\In\ServiceUidGeneratorInterface;
use App\Irc\Application\PublishedEvent\ServiceIntroductionRequestedEvent;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(DebugServBot::class)]
final class DebugServBotTest extends TestCase
{
    private const string UID = '001Z00009';

    private ActiveConnectionHolder $holder;

    protected function setUp(): void
    {
        $this->holder = new ActiveConnectionHolder();
    }

    private function bot(
        ?string $channel = '#DeBuG',
        bool $enabled = true,
        ?SendNoticePort $send = null,
        ?ChannelLookupPort $channels = null,
        ?ServiceChannelRegistrationPort $registration = null,
    ): DebugServBot {
        $uids = $this->createStub(ServiceUidGeneratorInterface::class);
        $uids->method('generateUid')->willReturn(self::UID);

        return new DebugServBot(
            $this->holder,
            $channels ?? $this->createStub(ChannelLookupPort::class),
            $registration ?? $this->createStub(ServiceChannelRegistrationPort::class),
            $send ?? $this->createStub(SendNoticePort::class),
            $uids,
            'services.sitiochat.org',
            $channel,
            $enabled,
        );
    }

    /** @param list<string> $prefixes */
    private function module(ProtocolServiceActionsInterface $actions, array $prefixes = ['q', 'o', 'v']): ProtocolModuleInterface
    {
        $modes = $this->createStub(ChannelModeSupportInterface::class);
        $modes->method('getSupportedPrefixModes')->willReturn($prefixes);
        $module = $this->createStub(ProtocolModuleInterface::class);
        $module->method('getServiceActions')->willReturn($actions);
        $module->method('getChannelModeSupport')->willReturn($modes);

        return $module;
    }

    private function sync(): NetworkSyncCompleteEvent
    {
        return new NetworkSyncCompleteEvent($this->createStub(ConnectionInterface::class), '001');
    }

    #[Test]
    public function subscribesToIntroductionAndSync(): void
    {
        self::assertSame([
            ServiceIntroductionRequestedEvent::class => ['onServiceIntroduction', 92],
            NetworkSyncCompleteEvent::class => ['onSyncComplete', -20],
        ], DebugServBot::getSubscribedEvents());
    }

    #[Test]
    public function exposesItsIdentity(): void
    {
        $bot = $this->bot();

        self::assertSame('debugserv', $bot->getServiceKey());
        self::assertSame('DeBuG', $bot->getNickname());
        self::assertSame('', $bot->getUid());
    }

    #[Test]
    public function isActiveOnlyWithAChannelAndEnabled(): void
    {
        self::assertTrue($this->bot()->isActive());
        self::assertFalse($this->bot(null)->isActive());
        self::assertFalse($this->bot('')->isActive());
        self::assertFalse($this->bot('#DeBuG', false)->isActive());
    }

    #[Test]
    public function introducesItselfAndJoinsTheChannelAfterSync(): void
    {
        $actions = $this->createMock(ProtocolServiceActionsInterface::class);
        $actions->expects(self::once())->method('introduceService')
            ->with('001', 'DeBuG', 'DeBuG', 'services.sitiochat.org', self::UID, 'Network Debug', 'debugserv');
        $actions->expects(self::once())->method('joinChannelAsService')->with('001', '#DeBuG', self::UID, 'q', 1234);
        $this->holder->setProtocolModule($this->module($actions));

        $channels = $this->createStub(ChannelLookupPort::class);
        $channels->method('findByChannelName')->willReturn(new ChannelView('#DeBuG', '+nt', null, 1, timestamp: 1234));
        $registration = $this->createMock(ServiceChannelRegistrationPort::class);
        $registration->expects(self::once())->method('registerServiceChannelJoin')->with('#DeBuG', self::UID, 'q', 1234);

        $bot = $this->bot(channels: $channels, registration: $registration);
        $bot->onServiceIntroduction(new ServiceIntroductionRequestedEvent('001'));
        $bot->onSyncComplete($this->sync());

        self::assertSame(self::UID, $bot->getUid());
    }

    #[Test]
    public function fallsBackToOpWhenNoKnownPrefixIsSupported(): void
    {
        $actions = $this->createMock(ProtocolServiceActionsInterface::class);
        $actions->expects(self::once())->method('joinChannelAsService')->with('001', '#DeBuG', self::UID, 'o', self::anything());
        $this->holder->setProtocolModule($this->module($actions, []));

        $bot = $this->bot();
        $bot->onServiceIntroduction(new ServiceIntroductionRequestedEvent('001'));
        $bot->onSyncComplete($this->sync());
    }

    #[Test]
    public function doesNothingWhenInactiveOrWithoutModule(): void
    {
        $actions = $this->createMock(ProtocolServiceActionsInterface::class);
        $actions->expects(self::never())->method('introduceService');
        $actions->expects(self::never())->method('joinChannelAsService');

        $withoutModule = $this->bot();
        $withoutModule->onServiceIntroduction(new ServiceIntroductionRequestedEvent('001'));
        $withoutModule->onSyncComplete($this->sync());

        $this->holder->setProtocolModule($this->module($actions));
        $disabled = $this->bot(enabled: false);
        $disabled->onServiceIntroduction(new ServiceIntroductionRequestedEvent('001'));
        $disabled->onSyncComplete($this->sync());

        // Active but never introduced: no UID to join with.
        $this->bot()->onSyncComplete($this->sync());
    }

    #[Test]
    public function saysThingsInTheChannelOnceIntroduced(): void
    {
        $actions = $this->createStub(ProtocolServiceActionsInterface::class);
        $this->holder->setProtocolModule($this->module($actions));
        $send = $this->createMock(SendNoticePort::class);
        $send->expects(self::once())->method('sendMessage')->with(self::UID, '#DeBuG', 'hola', 'PRIVMSG');

        $bot = $this->bot(send: $send);
        $bot->say('antes de presentarse');
        $bot->onServiceIntroduction(new ServiceIntroductionRequestedEvent('001'));
        $bot->say('hola');
    }
}
