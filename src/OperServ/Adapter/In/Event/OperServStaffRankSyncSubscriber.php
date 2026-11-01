<?php

declare(strict_types=1);

namespace App\OperServ\Adapter\In\Event;

use App\Irc\Application\PublishedEvent\NetworkSynchronizationCompletedEvent;
use App\OperServ\Application\Service\StaffRankRoleSync;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/** Brings the staff rank roles in line with the code once the link is synced. */
final readonly class OperServStaffRankSyncSubscriber implements EventSubscriberInterface
{
    public function __construct(private StaffRankRoleSync $sync) {}

    public static function getSubscribedEvents(): array
    {
        return [NetworkSynchronizationCompletedEvent::class => ['onSyncComplete', -30]];
    }

    public function onSyncComplete(NetworkSynchronizationCompletedEvent $event): void
    {
        $this->sync->sync();
    }
}
