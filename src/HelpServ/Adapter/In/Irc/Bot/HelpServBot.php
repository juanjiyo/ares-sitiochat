<?php

declare(strict_types=1);

namespace App\HelpServ\Adapter\In\Irc\Bot;

use App\HelpServ\Adapter\In\Irc\HelpServNotifierInterface;
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
 * HeLPServ pseudo-client: introduces on burst and sends replies to users.
 */
final class HelpServBot implements HelpServNotifierInterface, ServiceNicknameProviderInterface, ServiceUidProviderInterface, EventSubscriberInterface
{
    private string $uid = '';

    public function __construct(
        private readonly ActiveProtocolModuleHolderInterface $connectionHolder,
        private readonly SendNoticePort $sendNoticePort,
        private readonly ServiceUidGeneratorInterface $uidGenerator,
        private readonly string $servicesVhost,
        private readonly string $helpservNick = 'HeLP',
        private readonly string $helpservIdent = 'HeLP',
        private readonly string $helpservRealname = 'Help Service',
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
        $this->uid = $this->uidGenerator->generateUid('helpserv');
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
            $this->helpservNick,
            $this->helpservIdent,
            $this->servicesVhost,
            $this->uid,
            $this->helpservRealname,
            $this->getServiceKey(),
        );

        $this->logger->info('HeLP introduced to network.', [
            'uid' => $this->uid,
            'nick' => $this->helpservNick,
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
        return $this->helpservNick;
    }

    public function getUid(): string
    {
        return $this->uid;
    }

    public function getServiceKey(): string
    {
        return 'helpserv';
    }

    public function getNickname(): string
    {
        return $this->helpservNick;
    }
}
