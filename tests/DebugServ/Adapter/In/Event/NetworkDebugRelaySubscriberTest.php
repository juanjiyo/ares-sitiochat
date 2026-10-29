<?php

declare(strict_types=1);

namespace App\Tests\DebugServ\Adapter\In\Event;

use App\DebugServ\Adapter\In\Event\NetworkDebugRelaySubscriber;
use App\DebugServ\Adapter\In\Irc\Bot\DebugServBot;
use App\DebugServ\Adapter\In\Irc\NetworkEventTranslator;
use App\Irc\Adapter\Event\ConnectionLostEvent;
use App\Irc\Adapter\Event\IncomingIrcMessageEvent;
use App\Irc\Adapter\Event\NetworkSyncCompleteEvent;
use App\Irc\Adapter\Out\Connection\ActiveConnectionHolder;
use App\Irc\Adapter\Out\Connection\ConnectionInterface;
use App\Irc\Adapter\Protocol\IRCMessage;
use App\Irc\Application\Port\In\ChannelLookupPort;
use App\Irc\Application\Port\In\ChannelModeSupportInterface;
use App\Irc\Application\Port\In\NetworkUserLookupPort;
use App\Irc\Application\Port\In\ProtocolModuleInterface;
use App\Irc\Application\Port\In\ProtocolServiceActionsInterface;
use App\Irc\Application\Port\In\SendNoticePort;
use App\Irc\Application\Port\In\ServiceChannelRegistrationPort;
use App\Irc\Application\Port\In\ServiceUidGeneratorInterface;
use App\Irc\Application\PublishedEvent\ServiceIntroductionRequestedEvent;
use App\Irc\Domain\Server\ServerLink;
use App\Irc\Domain\ValueObject\Hostname;
use App\Irc\Domain\ValueObject\LinkPassword;
use App\Irc\Domain\ValueObject\Port;
use App\Irc\Domain\ValueObject\ServerName;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(NetworkDebugRelaySubscriber::class)]
final class NetworkDebugRelaySubscriberTest extends TestCase
{
    /** @var list<string> */
    private array $said = [];

    private function relay(string $disabled = '', bool $enabled = true): NetworkDebugRelaySubscriber
    {
        $holder = new ActiveConnectionHolder();
        $modes = $this->createStub(ChannelModeSupportInterface::class);
        $modes->method('getSupportedPrefixModes')->willReturn(['o']);
        $module = $this->createStub(ProtocolModuleInterface::class);
        $module->method('getServiceActions')->willReturn($this->createStub(ProtocolServiceActionsInterface::class));
        $module->method('getChannelModeSupport')->willReturn($modes);
        $holder->setProtocolModule($module);

        $uids = $this->createStub(ServiceUidGeneratorInterface::class);
        $uids->method('generateUid')->willReturn('001Z00009');
        $send = $this->createStub(SendNoticePort::class);
        $send->method('sendMessage')->willReturnCallback(function (string $from, string $to, string $text): void {
            $this->said[] = $text;
        });

        $bot = new DebugServBot(
            $holder,
            $this->createStub(ChannelLookupPort::class),
            $this->createStub(ServiceChannelRegistrationPort::class),
            $send,
            $uids,
            'services.sitiochat.org',
            '#DeBuG',
            $enabled,
        );
        $bot->onServiceIntroduction(new ServiceIntroductionRequestedEvent('001'));

        return new NetworkDebugRelaySubscriber(
            new NetworkEventTranslator($this->createStub(NetworkUserLookupPort::class)),
            $bot,
            $disabled,
        );
    }

    private static function incoming(string $raw): IncomingIrcMessageEvent
    {
        return new IncomingIrcMessageEvent(IRCMessage::fromRawLine($raw));
    }

    private function sync(NetworkDebugRelaySubscriber $relay): void
    {
        $relay->onSyncComplete(new NetworkSyncCompleteEvent($this->createStub(ConnectionInterface::class), '001'));
    }

    #[Test]
    public function subscribesBeforeTheProtocolHandlesTheLine(): void
    {
        self::assertSame([
            IncomingIrcMessageEvent::class => ['onIncomingMessage', 100],
            NetworkSyncCompleteEvent::class => ['onSyncComplete', -30],
            ConnectionLostEvent::class => ['onConnectionLost', 0],
        ], NetworkDebugRelaySubscriber::getSubscribedEvents());
    }

    #[Test]
    public function staysQuietDuringTheBurstAndAfterALostLink(): void
    {
        $relay = $this->relay();

        $relay->onIncomingMessage(self::incoming(':001 FOO burst'));
        $this->sync($relay);
        $relay->onIncomingMessage(self::incoming(':001 FOO live'));
        $relay->onConnectionLost(new ConnectionLostEvent(new ServerLink(
            serverName: new ServerName('services.sitiochat.org'),
            host: new Hostname('127.0.0.1'),
            port: new Port(7029),
            password: new LinkPassword('secret'),
            description: 'Ares',
            useTls: false,
        ), null));
        $relay->onIncomingMessage(self::incoming(':001 FOO after'));

        self::assertSame(['<><><> 001 FOO live'], $this->said);
    }

    #[Test]
    public function skipsDisabledEventTypesAndIgnoredLines(): void
    {
        $relay = $this->relay(' raw , part');
        $this->sync($relay);

        $relay->onIncomingMessage(self::incoming(':001 FOO x'));
        $relay->onIncomingMessage(self::incoming(':001AAAAAA PART #a'));
        $relay->onIncomingMessage(self::incoming(':001AAAAAA PRIVMSG NiCK :IDENTIFY x'));
        $relay->onIncomingMessage(self::incoming(':001AAAAAA JOIN #a'));

        self::assertCount(1, $this->said);
        self::assertStringContainsString('entra al canal', $this->said[0]);
    }

    #[Test]
    public function doesNothingWhenTheBotIsDisabled(): void
    {
        $relay = $this->relay(enabled: false);
        $this->sync($relay);
        $relay->onIncomingMessage(self::incoming(':001 FOO x'));

        self::assertSame([], $this->said);
    }
}
