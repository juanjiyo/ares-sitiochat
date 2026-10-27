<?php

declare(strict_types=1);

namespace App\GlobalServ\Adapter\In\Irc\Bot;

use App\GlobalServ\Adapter\In\Irc\GlobalServNotifierInterface;
use App\Irc\Application\Port\In\ActiveProtocolModuleHolderInterface;
use App\Irc\Application\Port\In\SendNoticePort;
use App\Irc\Application\Port\In\ServiceNicknameProviderInterface;
use App\Irc\Application\Port\In\ServiceUidGeneratorInterface;
use App\Irc\Application\Port\In\ServiceUidProviderInterface;
use App\Irc\Application\PublishedEvent\ServiceIntroductionRequestedEvent;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

final class GlobalServBot implements GlobalServNotifierInterface, ServiceNicknameProviderInterface, ServiceUidProviderInterface, EventSubscriberInterface
{
    private string $uid = '';

    public function __construct(
        private readonly ActiveProtocolModuleHolderInterface $connectionHolder,
        private readonly SendNoticePort $sendNoticePort,
        private readonly ServiceUidGeneratorInterface $uidGenerator,
        private readonly string $servicesVhost,
        private readonly string $globalServNick = 'GlobalServ',
        private readonly string $globalServIdent = 'GlobalServ',
        private readonly string $globalServRealname = 'Network Global Announcement Services',
        private readonly LoggerInterface $logger = new NullLogger(),
    ) {}

    public static function getSubscribedEvents(): array
    {
        return [
            ServiceIntroductionRequestedEvent::class => ['onBurstComplete', 85],
        ];
    }

    public function onBurstComplete(ServiceIntroductionRequestedEvent $event): void
    {
        $this->uid = $this->uidGenerator->generateUid('globalserv');
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
            $this->globalServNick,
            $this->globalServIdent,
            $this->servicesVhost,
            $this->uid,
            $this->globalServRealname,
            $this->getServiceKey(),
        );

        $this->logger->info('GlobalServ introduced to network.', [
            'uid' => $this->uid,
            'nick' => $this->globalServNick,
        ]);
    }

    public function getNick(): string
    {
        return $this->globalServNick;
    }

    public function getUid(): string
    {
        return $this->uid;
    }

    public function getServiceKey(): string
    {
        return 'globalserv';
    }

    public function getNickname(): string
    {
        return $this->globalServNick;
    }

    public function getVhost(): string
    {
        return $this->servicesVhost;
    }

    /**
     * @param 'NOTICE'|'PRIVMSG' $messageType
     */
    public function sendMessage(string $targetUidOrNick, string $message, string $messageType): void
    {
        $this->sendNoticePort->sendMessage($this->uid, $targetUidOrNick, $message, $messageType);
    }
}
