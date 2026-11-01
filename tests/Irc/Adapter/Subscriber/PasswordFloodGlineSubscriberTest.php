<?php

declare(strict_types=1);

namespace App\Tests\Irc\Adapter\Subscriber;

use App\Irc\Adapter\Event\ConnectionEstablishedEvent;
use App\Irc\Adapter\Event\ConnectionLostEvent;
use App\Irc\Adapter\Network\Event\GlineReceivedEvent;
use App\Irc\Adapter\Network\InMemoryPasswordFloodGlineRegistry;
use App\Irc\Adapter\Subscriber\PasswordFloodGlineSubscriber;
use App\Irc\Application\Port\In\ActiveConnectionHolderInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Clock\MockClock;

#[CoversClass(PasswordFloodGlineSubscriber::class)]
#[CoversClass(GlineReceivedEvent::class)]
final class PasswordFloodGlineSubscriberTest extends TestCase
{
    #[Test]
    public function subscribesToTklAndLinkChanges(): void
    {
        self::assertSame([
            GlineReceivedEvent::class => ['onGline', 0],
            ConnectionEstablishedEvent::class => ['onConnectionChanged', 0],
            ConnectionLostEvent::class => ['onConnectionChanged', 0],
        ], PasswordFloodGlineSubscriber::getSubscribedEvents());
    }

    #[Test]
    public function recordsOnlyGlinesSignedByThePasswordFloodProtection(): void
    {
        [$registry, $subscriber] = $this->subscriber();

        $subscriber->onGline(new GlineReceivedEvent(true, '*', '203.0.113.7', 'UDB:password-flood', 1800000000, 'Too many failed password attempts'));
        $subscriber->onGline(new GlineReceivedEvent(true, '*', '198.51.100.1', 'JuanJo', 0, 'manual'));
        $subscriber->onGline(new GlineReceivedEvent(true, '*', '192.0.2.1', 'UDB:managed', 0, 'services'));

        $glines = $registry->list();
        self::assertCount(1, $glines);
        self::assertSame('*@203.0.113.7', $glines[0]->mask());
        self::assertSame('Too many failed password attempts', $glines[0]->reason);
        self::assertSame(1800000000, $glines[0]->expiresAt?->getTimestamp());
    }

    #[Test]
    public function forgetsOnRemovalWhoeverRemovesIt(): void
    {
        [$registry, $subscriber] = $this->subscriber();
        $subscriber->onGline(new GlineReceivedEvent(true, '*', '203.0.113.7', 'UDB:password-flood', 0, 'r'));
        $subscriber->onGline(new GlineReceivedEvent(false, '*', '198.51.100.1', 'UDB:password-flood'));
        self::assertCount(1, $registry->list());

        $subscriber->onGline(new GlineReceivedEvent(false, '*', '203.0.113.7', 'UDB:password-flood'));

        self::assertSame([], $registry->list());
    }

    #[Test]
    public function emptiesTheMirrorWhenTheLinkChanges(): void
    {
        [$registry, $subscriber] = $this->subscriber();
        $subscriber->onGline(new GlineReceivedEvent(true, '*', '203.0.113.7', 'UDB:password-flood', 0, 'r'));

        $subscriber->onConnectionChanged();

        self::assertSame([], $registry->list());
    }

    /** @return array{InMemoryPasswordFloodGlineRegistry, PasswordFloodGlineSubscriber} */
    private function subscriber(): array
    {
        $registry = new InMemoryPasswordFloodGlineRegistry($this->createStub(ActiveConnectionHolderInterface::class), new MockClock('2026-11-01 10:00:00'));

        return [$registry, new PasswordFloodGlineSubscriber($registry)];
    }
}
