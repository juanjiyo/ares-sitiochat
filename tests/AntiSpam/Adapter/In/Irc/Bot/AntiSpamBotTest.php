<?php

declare(strict_types=1);

namespace App\Tests\AntiSpam\Adapter\In\Irc\Bot;

use App\AntiSpam\Adapter\In\Irc\Bot\AntiSpamBot;
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

#[CoversClass(AntiSpamBot::class)]
final class AntiSpamBotTest extends TestCase
{
    private const string ANTISPAM_UID = '001GHS';

    private const string HOSTNAME = 'services.example.com';

    private ActiveConnectionHolder $connectionHolder;

    private ServiceUidGeneratorInterface $uidGenerator;

    protected function setUp(): void
    {
        $this->connectionHolder = new ActiveConnectionHolder();
        $this->uidGenerator = $this->createStub(ServiceUidGeneratorInterface::class);
        $this->uidGenerator->method('generateUid')->willReturn(self::ANTISPAM_UID);
    }

    private function createBot(
        ?SendNoticePort $sendNoticePort = null,
        ?LoggerInterface $logger = null,
    ): AntiSpamBot {
        return new AntiSpamBot(
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
            AntiSpamBot::getSubscribedEvents(),
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
            'AntiSpam',
            'AntiSpam',
            self::HOSTNAME,
            self::ANTISPAM_UID,
            'Global Announcements',
            'antispam',
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

        self::assertSame(self::ANTISPAM_UID, $bot->getUid());
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
        $sendNoticePort->expects(self::once())->method('sendNotice')->with(self::ANTISPAM_UID, '001USER', 'Hi');

        $bot = $this->createBot($sendNoticePort);
        $bot->onBurstComplete(new ServiceIntroductionRequestedEvent('001'));

        $bot->sendNotice('001USER', 'Hi');
    }

    #[Test]
    public function sendMessageDelegatesToSendNoticePort(): void
    {
        $sendNoticePort = $this->createMock(SendNoticePort::class);
        $sendNoticePort->expects(self::once())->method('sendMessage')->with(self::ANTISPAM_UID, '001USER', 'Hi', 'PRIVMSG');

        $bot = $this->createBot($sendNoticePort);
        $bot->onBurstComplete(new ServiceIntroductionRequestedEvent('001'));

        $bot->sendMessage('001USER', 'Hi', 'PRIVMSG');
    }

    #[Test]
    public function getNickReturnsConfiguredNick(): void
    {
        $bot = $this->createBot();

        self::assertSame('AntiSpam', $bot->getNick());
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

        self::assertSame('antispam', $bot->getServiceKey());
    }

    #[Test]
    public function getNicknameReturnsConfiguredNick(): void
    {
        $bot = $this->createBot();

        self::assertSame('AntiSpam', $bot->getNickname());
    }
}
