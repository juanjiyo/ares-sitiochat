<?php

declare(strict_types=1);

namespace App\Irc\Adapter\In\Event;

use App\ChanServ\Application\Port\In\RegisteredChannelSetup;
use App\Irc\Adapter\Event\NetworkBurstCompleteEvent;
use App\Irc\Adapter\Event\NetworkSyncCompleteEvent;
use App\Irc\Application\Port\In\ActiveProtocolModuleHolderInterface;
use App\Irc\Application\Port\In\ChannelLookupPort;
use App\Irc\Application\Port\In\ServiceChannelRegistrationPort;
use App\Irc\Application\Port\In\ServiceDebugNotifierInterface;
use App\Irc\Application\Port\In\ServiceUidProviderInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

use function array_column;
use function array_keys;
use function in_array;
use function time;

/**
 * Joins the debug channel when services connect (if configured), applies
 * registered channel policies after sync completes, and seats every service
 * bot there with op (@): the service bots live in the debug channel, as
 * they did with the original dBoTs.
 */
final readonly class DebugChannelJoinSubscriber implements EventSubscriberInterface
{
    private const string PREFIX = 'o';

    /**
     * @param iterable<ServiceDebugNotifierInterface> $debugNotifiers
     * @param iterable<ServiceUidProviderInterface>   $serviceBots
     */
    public function __construct(
        private iterable $debugNotifiers,
        private ?string $debugChannel,
        private RegisteredChannelSetup $registeredChannelSetup,
        private iterable $serviceBots = [],
        private ?ActiveProtocolModuleHolderInterface $connectionHolder = null,
        private ?ChannelLookupPort $channelLookup = null,
        private ?ServiceChannelRegistrationPort $channelRegistration = null,
    ) {}

    public static function getSubscribedEvents(): array
    {
        return [
            NetworkBurstCompleteEvent::class => ['onBurstComplete', 0],
            NetworkSyncCompleteEvent::class => ['onSyncComplete', -30],
        ];
    }

    public function onBurstComplete(NetworkBurstCompleteEvent $event): void
    {
        foreach ($this->debugNotifiers as $notifier) {
            $notifier->ensureChannelJoined();
        }
    }

    public function onSyncComplete(NetworkSyncCompleteEvent $event): void
    {
        if (null === $this->debugChannel || '' === $this->debugChannel) {
            return;
        }

        $this->registeredChannelSetup->restore($this->debugChannel);
        $this->seatServiceBots($this->debugChannel, $event->serverSid);
    }

    private function seatServiceBots(string $channel, string $serverSid): void
    {
        $module = $this->connectionHolder?->getProtocolModule();
        if (null === $module || null === $this->channelLookup || null === $this->channelRegistration) {
            return;
        }

        $view = $this->channelLookup->findByChannelName($channel);
        $roles = array_column($view->members ?? [], 'roleLetter', 'uid');
        $seated = array_keys($roles);
        $timestamp = $view->timestamp ?? time();
        $actions = $module->getServiceActions();

        foreach ($this->serviceBots as $bot) {
            $uid = $bot->getUid();
            if ('' === $uid) {
                continue;
            }

            if (in_array($uid, $seated, true)) {
                $role = $roles[$uid] ?? '';
                if (self::PREFIX !== $role) {
                    if ('' !== $role) {
                        $actions->setChannelMemberMode($serverSid, $channel, $uid, $role, false, '', $timestamp);
                    }
                    $actions->setChannelMemberMode($serverSid, $channel, $uid, self::PREFIX, true, '', $timestamp);
                    $roles[$uid] = self::PREFIX;
                }

                continue;
            }

            $actions->joinChannelAsService($serverSid, $channel, $uid, self::PREFIX, $timestamp);
            $this->channelRegistration->registerServiceChannelJoin($channel, $uid, self::PREFIX, $timestamp);
            $seated[] = $uid;
            $roles[$uid] = self::PREFIX;
        }
    }
}
