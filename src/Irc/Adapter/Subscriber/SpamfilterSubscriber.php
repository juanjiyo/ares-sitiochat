<?php

declare(strict_types=1);

namespace App\Irc\Adapter\Subscriber;

use App\Irc\Adapter\Event\ConnectionEstablishedEvent;
use App\Irc\Adapter\Event\ConnectionLostEvent;
use App\Irc\Adapter\Network\Event\SpamfilterReceivedEvent;
use App\Irc\Adapter\Network\InMemorySpamfilterRegistry;
use App\Irc\Application\Port\In\Spamfilter;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Feeds the spamfilter mirror from the TKL F lines the IRCd sends, and empties
 * it on every link change (the IRCd re-sends them on link).
 */
final class SpamfilterSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private readonly InMemorySpamfilterRegistry $registry,
    ) {}

    public static function getSubscribedEvents(): array
    {
        return [
            SpamfilterReceivedEvent::class => ['onSpamfilter', 0],
            ConnectionEstablishedEvent::class => ['onConnectionChanged', 0],
            ConnectionLostEvent::class => ['onConnectionChanged', 0],
        ];
    }

    public function onSpamfilter(SpamfilterReceivedEvent $event): void
    {
        if (!$event->added) {
            $this->registry->forget($event->targets, $event->action, $event->match);

            return;
        }

        $this->registry->record(new Spamfilter(
            $event->targets,
            $event->action,
            $event->matchType,
            $event->match,
            $event->banDuration,
            $event->reason,
            $event->setBy,
        ));
    }

    public function onConnectionChanged(): void
    {
        $this->registry->clear();
    }
}
