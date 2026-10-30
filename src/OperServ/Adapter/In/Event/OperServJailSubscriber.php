<?php

declare(strict_types=1);

namespace App\OperServ\Adapter\In\Event;

use App\Irc\Application\PublishedEvent\ChannelSynchronizedEvent;
use App\Irc\Application\PublishedEvent\UserDepartedChannelEvent;
use App\Irc\Application\PublishedEvent\UserJoinedChannelEvent;
use App\Irc\Application\PublishedEvent\UserLeftNetworkEvent;
use App\OperServ\Application\UseCase\Jail\EnforceJail;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/** Forwards channel and network movements of users to the OPeR jail. */
final readonly class OperServJailSubscriber implements EventSubscriberInterface
{
    public function __construct(private EnforceJail $jail) {}

    public static function getSubscribedEvents(): array
    {
        return [
            UserJoinedChannelEvent::class => ['onUserJoinedChannel', 0],
            UserDepartedChannelEvent::class => ['onUserDepartedChannel', 0],
            UserLeftNetworkEvent::class => ['onUserLeftNetwork', 0],
            ChannelSynchronizedEvent::class => ['onChannelSynchronized', 0],
        ];
    }

    public function onUserJoinedChannel(UserJoinedChannelEvent $event): void
    {
        $this->jail->userJoined($event->uid, $event->channelName);
    }

    public function onUserDepartedChannel(UserDepartedChannelEvent $event): void
    {
        $this->jail->userDeparted($event->uid, $event->channelName);
    }

    public function onUserLeftNetwork(UserLeftNetworkEvent $event): void
    {
        $this->jail->userLeftNetwork($event->uid);
    }

    public function onChannelSynchronized(ChannelSynchronizedEvent $event): void
    {
        $this->jail->channelSynchronized($event->channelName);
    }
}
