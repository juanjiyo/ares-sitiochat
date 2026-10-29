<?php

declare(strict_types=1);

namespace App\DebugServ\Adapter\In\Irc\Bot;

use App\Irc\Adapter\Event\NetworkSyncCompleteEvent;
use App\Irc\Application\Port\In\ActiveProtocolModuleHolderInterface;
use App\Irc\Application\Port\In\ChannelLookupPort;
use App\Irc\Application\Port\In\SendNoticePort;
use App\Irc\Application\Port\In\ServiceChannelRegistrationPort;
use App\Irc\Application\Port\In\ServiceNicknameProviderInterface;
use App\Irc\Application\Port\In\ServiceUidGeneratorInterface;
use App\Irc\Application\Port\In\ServiceUidProviderInterface;
use App\Irc\Application\PublishedEvent\ServiceIntroductionRequestedEvent;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

use function array_find;
use function in_array;
use function time;

/**
 * DeBuG pseudo-client: sits in the debug channel and writes what happens on the
 * network there, like the original `de.mrc`.
 */
final class DebugServBot implements ServiceNicknameProviderInterface, ServiceUidProviderInterface, EventSubscriberInterface
{
    private const array PREFIX_ORDER = ['q', 'a', 'o', 'h', 'v'];

    private string $uid = '';

    public function __construct(
        private readonly ActiveProtocolModuleHolderInterface $connectionHolder,
        private readonly ChannelLookupPort $channelLookup,
        private readonly ServiceChannelRegistrationPort $channelRegistration,
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
            NetworkSyncCompleteEvent::class => ['onSyncComplete', -20],
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

    public function onSyncComplete(NetworkSyncCompleteEvent $event): void
    {
        $module = $this->connectionHolder->getProtocolModule();
        if (!$this->isActive() || null === $module || '' === $this->uid || null === $this->debugChannel) {
            return;
        }

        $supported = $module->getChannelModeSupport()->getSupportedPrefixModes();
        $prefix = array_find(self::PREFIX_ORDER, static fn (string $letter): bool => in_array($letter, $supported, true)) ?? 'o';
        $timestamp = $this->channelLookup->findByChannelName($this->debugChannel)->timestamp ?? time();

        $module->getServiceActions()->joinChannelAsService($event->serverSid, $this->debugChannel, $this->uid, $prefix, $timestamp);
        $this->channelRegistration->registerServiceChannelJoin($this->debugChannel, $this->uid, $prefix, $timestamp);
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
