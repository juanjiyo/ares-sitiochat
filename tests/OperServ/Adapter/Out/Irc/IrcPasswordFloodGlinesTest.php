<?php

declare(strict_types=1);

namespace App\Tests\OperServ\Adapter\Out\Irc;

use App\Irc\Application\Port\In\PasswordFloodGline;
use App\Irc\Application\Port\In\PasswordFloodGlinePort;
use App\OperServ\Adapter\Out\Irc\IrcPasswordFloodGlines;
use App\OperServ\Application\Port\Out\IrcdGlineEntry;
use DateTimeImmutable;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(IrcPasswordFloodGlines::class)]
#[CoversClass(IrcdGlineEntry::class)]
final class IrcPasswordFloodGlinesTest extends TestCase
{
    #[Test]
    public function exposesTheIrcdMirrorAsOperServEntries(): void
    {
        $expiry = new DateTimeImmutable('2026-11-01 11:00:00');
        $port = new RecordingPasswordFloodGlinePort([new PasswordFloodGline('*', '203.0.113.7', 'Too many failed password attempts', $expiry)]);

        $entries = new IrcPasswordFloodGlines($port)->all();

        self::assertCount(1, $entries);
        self::assertSame('*@203.0.113.7', $entries[0]->mask);
        self::assertSame('Too many failed password attempts', $entries[0]->reason);
        self::assertSame($expiry, $entries[0]->expiresAt);
    }

    #[Test]
    public function removesTheMatchingGlineThroughTheIrcd(): void
    {
        $match = new PasswordFloodGline('*', 'Host.Example', 'r', null);
        $port = new RecordingPasswordFloodGlinePort([new PasswordFloodGline('*', '198.51.100.1', 'r', null), $match]);

        self::assertTrue(new IrcPasswordFloodGlines($port)->remove('*@host.example'));
        self::assertSame([$match], $port->removed);
    }

    #[Test]
    public function reportsFalseWhenNoGlineHasThatMask(): void
    {
        $port = new RecordingPasswordFloodGlinePort([new PasswordFloodGline('*', '198.51.100.1', 'r', null)]);

        self::assertFalse(new IrcPasswordFloodGlines($port)->remove('*@203.0.113.7'));
        self::assertSame([], $port->removed);
    }
}

final class RecordingPasswordFloodGlinePort implements PasswordFloodGlinePort
{
    /** @var list<PasswordFloodGline> */
    public array $removed = [];

    /** @param list<PasswordFloodGline> $glines */
    public function __construct(private readonly array $glines) {}

    public function list(): array
    {
        return $this->glines;
    }

    public function remove(PasswordFloodGline $gline): void
    {
        $this->removed[] = $gline;
    }
}
