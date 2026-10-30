<?php

declare(strict_types=1);

namespace App\OperServ\Adapter\In\Event;

use App\Irc\Application\PublishedEvent\NetworkSynchronizationCompletedEvent;
use App\OperServ\Application\UseCase\ManageEline\ManageElineHandler;
use DateTimeImmutable;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/** Publishes the active E-Lines again once the link is synced, in case the IRCd lost them. */
final readonly class OperServElineSyncSubscriber implements EventSubscriberInterface
{
    public function __construct(private ManageElineHandler $elines) {}

    public static function getSubscribedEvents(): array
    {
        return [NetworkSynchronizationCompletedEvent::class => ['onSyncComplete', -40]];
    }

    public function onSyncComplete(NetworkSynchronizationCompletedEvent $event): void
    {
        $this->elines->resync(new DateTimeImmutable());
    }
}
