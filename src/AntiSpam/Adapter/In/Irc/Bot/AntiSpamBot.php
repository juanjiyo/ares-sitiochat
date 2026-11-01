<?php

declare(strict_types=1);

namespace App\AntiSpam\Adapter\In\Irc\Bot;

use App\AntiSpam\Adapter\In\Irc\AntiSpamNotifierInterface;
use App\Irc\Application\Port\In\ActiveProtocolModuleHolderInterface;
use App\Irc\Application\Port\In\SendNoticePort;
use App\Irc\Application\Port\In\ServiceNicknameProviderInterface;
use App\Irc\Application\Port\In\ServiceUidGeneratorInterface;
use App\Irc\Application\Port\In\ServiceUidProviderInterface;
use App\Irc\Application\PublishedEvent\ServiceIntroductionRequestedEvent;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * AntiSpam pseudo-client: introduces on burst and sends replies to users.
 */
final class AntiSpamBot implements AntiSpamNotifierInterface, ServiceNicknameProviderInterface, ServiceUidProviderInterface, EventSubscriberInterface
{
    private string $uid = '';

    public function __construct(
        private readonly ActiveProtocolModuleHolderInterface $connectionHolder,
        private readonly SendNoticePort $sendNoticePort,
        private readonly ServiceUidGeneratorInterface $uidGenerator,
        private readonly string $servicesVhost,
        private readonly string $antispamNick = 'AntiSpam',
        private readonly string $antispamIdent = 'AntiSpam',
        private readonly string $antispamRealname = 'Global Announcements',
        private readonly LoggerInterface $logger = new NullLogger(),
    ) {}

    public static function getSubscribedEvents(): array
    {
        return [
            ServiceIntroductionRequestedEvent::class => ['onBurstComplete', 93],
        ];
    }

    public function onBurstComplete(ServiceIntroductionRequestedEvent $event): void
    {
        $this->uid = $this->uidGenerator->generateUid('antispam');
        $this->introduce($event->serverSid);
    }

    private function introduce(string $serverSid): void
    {
        $module = $this->connectionHolder->getProtocolModule();
        if (null === $module) {
            return;
        }

        $module->getServiceActions()->introduceService(
            $serverSid,
            $this->antispamNick,
            $this->antispamIdent,
            $this->servicesVhost,
            $this->uid,
            $this->antispamRealname,
            $this->getServiceKey(),
        );

        $this->logger->info('AntiSpam introduced to network.', [
            'uid' => $this->uid,
            'nick' => $this->antispamNick,
        ]);
    }

    public function sendNotice(string $targetUidOrNick, string $message): void
    {
        $this->sendNoticePort->sendNotice($this->uid, $targetUidOrNick, $message);
    }

    /**
     * @param 'NOTICE'|'PRIVMSG' $messageType
     */
    public function sendMessage(string $targetUidOrNick, string $message, string $messageType): void
    {
        $this->sendNoticePort->sendMessage($this->uid, $targetUidOrNick, $message, $messageType);
    }

    public function getNick(): string
    {
        return $this->antispamNick;
    }

    public function getUid(): string
    {
        return $this->uid;
    }

    public function getServiceKey(): string
    {
        return 'antispam';
    }

    public function getNickname(): string
    {
        return $this->antispamNick;
    }
}
