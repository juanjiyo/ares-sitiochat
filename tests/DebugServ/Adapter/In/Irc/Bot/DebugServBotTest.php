<?php

declare(strict_types=1);

namespace App\Tests\DebugServ\Adapter\In\Irc\Bot;

use App\DebugServ\Adapter\In\Irc\Bot\DebugServBot;
use App\Irc\Adapter\Out\Connection\ActiveConnectionHolder;
use App\Irc\Application\Port\In\ProtocolModuleInterface;
use App\Irc\Application\Port\In\ProtocolServiceActionsInterface;
use App\Irc\Application\Port\In\SendNoticePort;
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

    private function bot(?string $channel = '#DeBuG', bool $enabled = true, ?SendNoticePort $send = null): DebugServBot
    {
        $uids = $this->createStub(ServiceUidGeneratorInterface::class);
        $uids->method('generateUid')->willReturn(self::UID);

        return new DebugServBot(
            $this->holder,
            $send ?? $this->createStub(SendNoticePort::class),
            $uids,
            'services.sitiochat.org',
            $channel,
            $enabled,
        );
    }

    private function module(ProtocolServiceActionsInterface $actions): ProtocolModuleInterface
    {
        $module = $this->createStub(ProtocolModuleInterface::class);
        $module->method('getServiceActions')->willReturn($actions);

        return $module;
    }

    #[Test]
    public function subscribesToTheIntroduction(): void
    {
        self::assertSame(
            [ServiceIntroductionRequestedEvent::class => ['onServiceIntroduction', 92]],
            DebugServBot::getSubscribedEvents(),
        );
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
    public function introducesItself(): void
    {
        $actions = $this->createMock(ProtocolServiceActionsInterface::class);
        $actions->expects(self::once())->method('introduceService')
            ->with('001', 'DeBuG', 'DeBuG', 'services.sitiochat.org', self::UID, 'Network Debug', 'debugserv');
        $this->holder->setProtocolModule($this->module($actions));

        $bot = $this->bot();
        $bot->onServiceIntroduction(new ServiceIntroductionRequestedEvent('001'));

        self::assertSame(self::UID, $bot->getUid());
    }

    #[Test]
    public function doesNotIntroduceItselfWhenInactiveOrWithoutModule(): void
    {
        $this->bot()->onServiceIntroduction(new ServiceIntroductionRequestedEvent('001'));

        $actions = $this->createMock(ProtocolServiceActionsInterface::class);
        $actions->expects(self::never())->method('introduceService');
        $this->holder->setProtocolModule($this->module($actions));

        $disabled = $this->bot(enabled: false);
        $disabled->onServiceIntroduction(new ServiceIntroductionRequestedEvent('001'));

        self::assertSame('', $disabled->getUid());
    }

    #[Test]
    public function saysThingsInTheChannelOnceIntroduced(): void
    {
        $this->holder->setProtocolModule($this->module($this->createStub(ProtocolServiceActionsInterface::class)));
        $send = $this->createMock(SendNoticePort::class);
        $send->expects(self::once())->method('sendMessage')->with(self::UID, '#DeBuG', 'hola', 'PRIVMSG');

        $bot = $this->bot(send: $send);
        $bot->say('antes de presentarse');
        $bot->onServiceIntroduction(new ServiceIntroductionRequestedEvent('001'));
        $bot->say('hola');
    }
}
