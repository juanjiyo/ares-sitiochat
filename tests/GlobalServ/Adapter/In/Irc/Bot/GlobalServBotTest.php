<?php

declare(strict_types=1);

namespace App\Tests\GlobalServ\Adapter\In\Irc\Bot;

use App\GlobalServ\Adapter\In\Irc\Bot\GlobalServBot;
use App\Irc\Adapter\Out\Connection\ActiveConnectionHolder;
use App\Irc\Application\Port\In\ProtocolModuleInterface;
use App\Irc\Application\Port\In\ProtocolServiceActionsInterface;
use App\Irc\Application\Port\In\SendNoticePort;
use App\Irc\Application\Port\In\ServiceUidGeneratorInterface;
use App\Irc\Application\PublishedEvent\ServiceIntroductionRequestedEvent;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

#[CoversClass(GlobalServBot::class)]
final class GlobalServBotTest extends TestCase
{
    private const string GLOBALSERV_UID = '001GHS';

    private const string HOSTNAME = 'services.example.com';

    private ActiveConnectionHolder $connectionHolder;

    private ServiceUidGeneratorInterface $uidGenerator;

    protected function setUp(): void
    {
        $this->connectionHolder = new ActiveConnectionHolder();
        $this->uidGenerator = $this->createStub(ServiceUidGeneratorInterface::class);
        $this->uidGenerator->method('generateUid')->willReturn(self::GLOBALSERV_UID);
    }

    private function createBot(
        ?SendNoticePort $sendNoticePort = null,
        ?LoggerInterface $logger = null,
    ): GlobalServBot {
        return new GlobalServBot(
            $this->connectionHolder,
            $sendNoticePort ?? $this->createStub(SendNoticePort::class),
            $this->uidGenerator,
            self::HOSTNAME,
            logger: $logger ?? $this->createStub(LoggerInterface::class),
        );
    }

    #[Test]
    public function getSubscribedEventsReturnsBurstCompleteWithPriority(): void
    {
        self::assertSame(
            [ServiceIntroductionRequestedEvent::class => ['onBurstComplete', 93]],
            GlobalServBot::getSubscribedEvents(),
        );
    }

    #[Test]
    public function onBurstCompleteIntroducesTheServiceWithNickIdentAndRealname(): void
    {
        $bot = $this->createBot();
        $bot->onBurstComplete(new ServiceIntroductionRequestedEvent('001'));

        $serviceActions = $this->createMock(ProtocolServiceActionsInterface::class);
        $serviceActions->expects(self::once())->method('introduceService')->with(
            '001',
            'GlobalServ',
            'GlobalServ',
            self::HOSTNAME,
            self::GLOBALSERV_UID,
            'Global Announcements',
            'globalserv',
        );
        $module = $this->createStub(ProtocolModuleInterface::class);
        $module->method('getServiceActions')->willReturn($serviceActions);

        $this->connectionHolder->setProtocolModule($module);

        $bot->onBurstComplete(new ServiceIntroductionRequestedEvent('001'));
    }

    #[Test]
    public function onBurstCompleteStoresTheGeneratedUid(): void
    {
        $bot = $this->createBot();
        $bot->onBurstComplete(new ServiceIntroductionRequestedEvent('001'));

        self::assertSame(self::GLOBALSERV_UID, $bot->getUid());
    }

    #[Test]
    public function onBurstCompleteReturnsEarlyWhenModuleIsNull(): void
    {
        $bot = $this->createBot();

        $bot->onBurstComplete(new ServiceIntroductionRequestedEvent('001'));

        self::assertNull($this->connectionHolder->getProtocolModule());
    }

    #[Test]
    public function sendNoticeDelegatesToSendNoticePort(): void
    {
        $sendNoticePort = $this->createMock(SendNoticePort::class);
        $sendNoticePort->expects(self::once())->method('sendNotice')->with(self::GLOBALSERV_UID, '001USER', 'Hi');

        $bot = $this->createBot($sendNoticePort);
        $bot->onBurstComplete(new ServiceIntroductionRequestedEvent('001'));

        $bot->sendNotice('001USER', 'Hi');
    }

    #[Test]
    public function sendMessageDelegatesToSendNoticePort(): void
    {
        $sendNoticePort = $this->createMock(SendNoticePort::class);
        $sendNoticePort->expects(self::once())->method('sendMessage')->with(self::GLOBALSERV_UID, '001USER', 'Hi', 'PRIVMSG');

        $bot = $this->createBot($sendNoticePort);
        $bot->onBurstComplete(new ServiceIntroductionRequestedEvent('001'));

        $bot->sendMessage('001USER', 'Hi', 'PRIVMSG');
    }

    #[Test]
    public function getNickReturnsConfiguredNick(): void
    {
        $bot = $this->createBot();

        self::assertSame('GlobalServ', $bot->getNick());
    }

    #[Test]
    public function getUidReturnsEmptyStringBeforeTheBurst(): void
    {
        $bot = $this->createBot();

        self::assertSame('', $bot->getUid());
    }

    #[Test]
    public function getServiceKeyReturnsGlobalserv(): void
    {
        $bot = $this->createBot();

        self::assertSame('globalserv', $bot->getServiceKey());
    }

    #[Test]
    public function getNicknameReturnsConfiguredNick(): void
    {
        $bot = $this->createBot();

        self::assertSame('GlobalServ', $bot->getNickname());
    }
}
