<?php

declare(strict_types=1);

namespace App\Tests\Irc\Adapter\Network;

use App\Irc\Adapter\Network\InMemoryPasswordFloodGlineRegistry;
use App\Irc\Application\Port\In\ActiveConnectionHolderInterface;
use App\Irc\Application\Port\In\PasswordFloodGline;
use DateTimeImmutable;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Clock\MockClock;

#[CoversClass(InMemoryPasswordFloodGlineRegistry::class)]
#[CoversClass(PasswordFloodGline::class)]
final class InMemoryPasswordFloodGlineRegistryTest extends TestCase
{
    #[Test]
    public function listsRecordedGlinesInArrivalOrderAndReplacesTheSameMask(): void
    {
        $registry = $this->registry(new RecordingConnection());
        $first = $this->gline('203.0.113.7', '2026-11-01 11:00:00');
        $second = $this->gline('198.51.100.1', null);
        $registry->record($first);
        $registry->record($second);
        $replacement = new PasswordFloodGline('*', '203.0.113.7', 'again', null);
        $registry->record($replacement);

        self::assertSame([$replacement, $second], $registry->list());
        self::assertSame('*@203.0.113.7', $first->mask());
    }

    #[Test]
    public function dropsEntriesOnceTheirExpiryIsReached(): void
    {
        $registry = $this->registry(new RecordingConnection());
        $expired = $this->gline('203.0.113.7', '2026-11-01 10:00:00');
        $active = $this->gline('198.51.100.1', '2026-11-01 10:00:01');
        $registry->record($expired);
        $registry->record($active);

        self::assertSame([$active], $registry->list());
    }

    #[Test]
    public function forgetsByCaseInsensitiveMaskAndClearsEverything(): void
    {
        $registry = $this->registry(new RecordingConnection());
        $registry->record(new PasswordFloodGline('*', 'Host.Example', 'r', null));
        $registry->record($this->gline('198.51.100.1', null));

        $registry->forget('*', 'host.example');
        self::assertCount(1, $registry->list());

        $registry->clear();
        self::assertSame([], $registry->list());
    }

    #[Test]
    public function removeAsksTheIrcdAndForgetsTheEntry(): void
    {
        $connection = new RecordingConnection('001');
        $registry = $this->registry($connection);
        $gline = $this->gline('203.0.113.7', null);
        $registry->record($gline);

        $registry->remove($gline);

        self::assertSame([':001 TKL - G * 203.0.113.7 UDB:password-flood'], $connection->lines);
        self::assertSame([], $registry->list());
    }

    #[Test]
    public function removeWithoutLinkOnlyForgetsTheEntry(): void
    {
        $withoutSid = new RecordingConnection(null);
        $disconnected = new RecordingConnection('001', false);
        foreach ([$withoutSid, $disconnected] as $connection) {
            $registry = $this->registry($connection);
            $gline = $this->gline('203.0.113.7', null);
            $registry->record($gline);
            $registry->remove($gline);

            self::assertSame([], $connection->lines);
            self::assertSame([], $registry->list());
        }
    }

    #[Test]
    public function expiryTreatsZeroAsPermanent(): void
    {
        self::assertNull(InMemoryPasswordFloodGlineRegistry::expiry(0));
        self::assertSame(1800000000, InMemoryPasswordFloodGlineRegistry::expiry(1800000000)?->getTimestamp());
    }

    private function registry(RecordingConnection $connection): InMemoryPasswordFloodGlineRegistry
    {
        return new InMemoryPasswordFloodGlineRegistry($connection, new MockClock('2026-11-01 10:00:00'));
    }

    private function gline(string $ip, ?string $expiresAt): PasswordFloodGline
    {
        return new PasswordFloodGline('*', $ip, 'Too many failed password attempts', null === $expiresAt ? null : new DateTimeImmutable($expiresAt));
    }
}

final class RecordingConnection implements ActiveConnectionHolderInterface
{
    /** @var list<string> */
    public array $lines = [];

    public function __construct(
        private readonly ?string $sid = '001',
        private readonly bool $connected = true,
    ) {}

    public function getServerSid(): ?string
    {
        return $this->sid;
    }

    public function writeLine(string $line): void
    {
        $this->lines[] = $line;
    }

    public function isConnected(): bool
    {
        return $this->connected;
    }
}
