<?php

declare(strict_types=1);

namespace App\Tests\Irc\Adapter\Subscriber;

use App\Irc\Adapter\Event\ConnectionEstablishedEvent;
use App\Irc\Adapter\Event\ConnectionLostEvent;
use App\Irc\Adapter\Network\Event\SpamfilterReceivedEvent;
use App\Irc\Adapter\Network\InMemorySpamfilterRegistry;
use App\Irc\Adapter\Subscriber\SpamfilterSubscriber;
use App\Irc\Application\Port\In\ActiveConnectionHolderInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Clock\MockClock;

#[CoversClass(SpamfilterSubscriber::class)]
#[CoversClass(SpamfilterReceivedEvent::class)]
final class SpamfilterSubscriberTest extends TestCase
{
    #[Test]
    public function subscribesToSpamfiltersAndLinkChanges(): void
    {
        self::assertSame([
            SpamfilterReceivedEvent::class => ['onSpamfilter', 0],
            ConnectionEstablishedEvent::class => ['onConnectionChanged', 0],
            ConnectionLostEvent::class => ['onConnectionChanged', 0],
        ], SpamfilterSubscriber::getSubscribedEvents());
    }

    #[Test]
    public function recordsAddedSpamfiltersWithAllTheirFields(): void
    {
        [$registry, $subscriber] = $this->subscriber();

        $subscriber->onSpamfilter(new SpamfilterReceivedEvent(true, 'cpn', 'g', 'JuanJo', 'regex', 'casino[0-9]+', 3600, 'Casino spam'));

        $list = $registry->list();
        self::assertCount(1, $list);
        self::assertSame('cpn', $list[0]->targets);
        self::assertSame('g', $list[0]->action);
        self::assertSame('regex', $list[0]->matchType);
        self::assertSame('casino[0-9]+', $list[0]->match);
        self::assertSame(3600, $list[0]->banDuration);
        self::assertSame('Casino spam', $list[0]->reason);
        self::assertSame('JuanJo', $list[0]->setBy);
    }

    #[Test]
    public function forgetsOnRemovalWhoeverRemovesIt(): void
    {
        [$registry, $subscriber] = $this->subscriber();
        $subscriber->onSpamfilter(new SpamfilterReceivedEvent(true, 'c', 'w', 'Oper', 'simple', '*x*', 0, 'r'));

        $subscriber->onSpamfilter(new SpamfilterReceivedEvent(false, 'c', 'w', 'SomeoneElse', 'simple', '*x*', 0, 'r'));

        self::assertSame([], $registry->list());
    }

    #[Test]
    public function emptiesTheMirrorWhenTheLinkChanges(): void
    {
        [$registry, $subscriber] = $this->subscriber();
        $subscriber->onSpamfilter(new SpamfilterReceivedEvent(true, 'c', 'w', 'Oper', 'simple', '*x*', 0, 'r'));

        $subscriber->onConnectionChanged();

        self::assertSame([], $registry->list());
    }

    /** @return array{InMemorySpamfilterRegistry, SpamfilterSubscriber} */
    private function subscriber(): array
    {
        $registry = new InMemorySpamfilterRegistry($this->createStub(ActiveConnectionHolderInterface::class), new MockClock());

        return [$registry, new SpamfilterSubscriber($registry)];
    }
}
