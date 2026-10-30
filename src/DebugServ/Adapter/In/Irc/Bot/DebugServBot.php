<?php

declare(strict_types=1);

namespace App\DebugServ\Adapter\In\Irc\Bot;

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
 * DeBuG pseudo-client: writes what happens on the network in the debug channel,
 * like the original `de.mrc`. It is seated there with the other service bots.
 */
final class DebugServBot implements ServiceNicknameProviderInterface, ServiceUidProviderInterface, EventSubscriberInterface
{
    private string $uid = '';

    public function __construct(
        private readonly ActiveProtocolModuleHolderInterface $connectionHolder,
        private readonly SendNoticePort $sendNoticePort,
        private readonly ServiceUidGeneratorInterface $uidGenerator,
        private readonly string $servicesVhost,
        private readonly ?string $debugChannel,
        private readonly bool $enabled = true,
        private readonly string $nick = 'DeBuG',
        private readonly string $ident = 'DeBuG',
        private readonly string $realname = 'Network Debug',
        private readonly LoggerInterface $logger = new NullLogger(),
    ) {}

    public static function getSubscribedEvents(): array
    {
        return [
            ServiceIntroductionRequestedEvent::class => ['onServiceIntroduction', 92],
        ];
    }

    public function isActive(): bool
    {
        return $this->enabled && null !== $this->debugChannel && '' !== $this->debugChannel;
    }

    public function onServiceIntroduction(ServiceIntroductionRequestedEvent $event): void
    {
        $module = $this->connectionHolder->getProtocolModule();
        if (!$this->isActive() || null === $module) {
            return;
        }

        $this->uid = $this->uidGenerator->generateUid($this->getServiceKey());
        $module->getServiceActions()->introduceService(
            $event->serverSid,
            $this->nick,
            $this->ident,
            $this->servicesVhost,
            $this->uid,
            $this->realname,
            $this->getServiceKey(),
        );

        $this->logger->info('DeBuG introduced to network.', ['uid' => $this->uid, 'nick' => $this->nick]);
    }

    public function say(string $text): void
    {
        if (!$this->isActive() || '' === $this->uid || null === $this->debugChannel) {
            return;
        }

        $this->sendNoticePort->sendMessage($this->uid, $this->debugChannel, $text, 'PRIVMSG');
    }

    public function getUid(): string
    {
        return $this->uid;
    }

    public function getServiceKey(): string
    {
        return 'debugserv';
    }

    public function getNickname(): string
    {
        return $this->nick;
    }
}
