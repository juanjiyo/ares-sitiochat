<?php

declare(strict_types=1);

namespace App\Tests\OperServ\Adapter\In\Event;

use App\Irc\Application\PublishedEvent\NetworkSynchronizationCompletedEvent;
use App\OperServ\Adapter\In\Event\OperServElineSyncSubscriber;
use App\OperServ\Application\Port\Out\ElineEntry;
use App\OperServ\Application\UseCase\ManageEline\ManageElineHandler;
use App\Tests\OperServ\Application\UseCase\ChannelOverride\RecordingCommandAuditRecorder;
use App\Tests\OperServ\Application\UseCase\ManageEline\InMemoryElineRepository;
use App\Tests\OperServ\Application\UseCase\ManageEline\RecordingElineNetwork;
use DateTimeImmutable;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(OperServElineSyncSubscriber::class)]
final class OperServElineSyncSubscriberTest extends TestCase
{
    #[Test]
    public function republishesTheElinesOnceTheLinkIsSynced(): void
    {
        self::assertSame([NetworkSynchronizationCompletedEvent::class => ['onSyncComplete', -40]], OperServElineSyncSubscriber::getSubscribedEvents());

        $elines = new InMemoryElineRepository();
        $elines->save(new ElineEntry('*@1.1.1.1', 'Oper', 'r', new DateTimeImmutable(), null));
        $network = new RecordingElineNetwork();

        new OperServElineSyncSubscriber(new ManageElineHandler($elines, $network, new RecordingCommandAuditRecorder()))
            ->onSyncComplete(new NetworkSynchronizationCompletedEvent('001'));

        self::assertSame([['add', '*@1.1.1.1']], $network->calls);
    }
}
