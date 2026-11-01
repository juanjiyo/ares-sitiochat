<?php

declare(strict_types=1);

namespace App\Irc\Adapter\Subscriber;

use App\Irc\Adapter\Event\ConnectionEstablishedEvent;
use App\Irc\Adapter\Event\ConnectionLostEvent;
use App\Irc\Adapter\Network\Event\GlineReceivedEvent;
use App\Irc\Adapter\Network\InMemoryPasswordFloodGlineRegistry;
use App\Irc\Application\Port\In\PasswordFloodGline;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Feeds the password-flood G-Line mirror from the TKL lines the IRCd sends,
 * and empties it on every link change (the IRCd re-sends them on link).
 */
final class PasswordFloodGlineSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private readonly InMemoryPasswordFloodGlineRegistry $registry,
    ) {}

    public static function getSubscribedEvents(): array
    {
        return [
            GlineReceivedEvent::class => ['onGline', 0],
            ConnectionEstablishedEvent::class => ['onConnectionChanged', 0],
            ConnectionLostEvent::class => ['onConnectionChanged', 0],
        ];
    }

    public function onGline(GlineReceivedEvent $event): void
    {
        if (InMemoryPasswordFloodGlineRegistry::SET_BY !== $event->setBy) {
            return;
        }

        if (!$event->added) {
            $this->registry->forget($event->user, $event->host);

            return;
        }

        $this->registry->record(new PasswordFloodGline(
            $event->user,
            $event->host,
            $event->reason,
            InMemoryPasswordFloodGlineRegistry::expiry($event->expiresAt),
        ));
    }

    public function onConnectionChanged(): void
    {
        $this->registry->clear();
    }
}
