<?php

declare(strict_types=1);

namespace App\Tests\OperServ\Adapter\Out\Irc;

use App\Irc\Application\Port\In\Spamfilter;
use App\Irc\Application\Port\In\SpamfilterPort;
use App\OperServ\Adapter\Out\Irc\IrcNetworkSpamfilters;
use App\OperServ\Application\Port\Out\SpamfilterEntry;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(IrcNetworkSpamfilters::class)]
#[CoversClass(SpamfilterEntry::class)]
final class IrcNetworkSpamfiltersTest extends TestCase
{
    #[Test]
    public function exposesTheMirrorAsOperServEntries(): void
    {
        $port = new RecordingSpamfilterPort([new Spamfilter('cpn', 'g', 'regex', 'casino[0-9]+', 3600, 'Casino', 'JuanJo')], true);

        $entries = new IrcNetworkSpamfilters($port)->all();

        self::assertCount(1, $entries);
        self::assertSame(['cpn', 'g', 'regex', 'casino[0-9]+', 3600, 'Casino', 'JuanJo'], [
            $entries[0]->targets, $entries[0]->action, $entries[0]->matchType, $entries[0]->match,
            $entries[0]->banDuration, $entries[0]->reason, $entries[0]->setBy,
        ]);
    }

    #[Test]
    public function forwardsAddAndRemoveWithAllFieldsAndTheirResult(): void
    {
        $entry = new SpamfilterEntry('c', 'w', 'simple', '*x*', 86400, 'Spam', 'Oper');
        foreach ([true, false] as $linked) {
            $port = new RecordingSpamfilterPort([], $linked);
            $network = new IrcNetworkSpamfilters($port);

            self::assertSame($linked, $network->add($entry));
            self::assertSame($linked, $network->remove($entry, 'JuanJo'));

            $expected = new Spamfilter('c', 'w', 'simple', '*x*', 86400, 'Spam', 'Oper');
            self::assertSame([(array) $expected], array_map(static fn (Spamfilter $s): array => (array) $s, $port->added));
            self::assertSame([(array) $expected], array_map(static fn (Spamfilter $s): array => (array) $s, $port->removed));
            self::assertSame(['JuanJo'], $port->removedBy);
        }
    }
}

final class RecordingSpamfilterPort implements SpamfilterPort
{
    /** @var list<Spamfilter> */
    public array $added = [];

    /** @var list<Spamfilter> */
    public array $removed = [];

    /** @var list<string> */
    public array $removedBy = [];

    /** @param list<Spamfilter> $spamfilters */
    public function __construct(private readonly array $spamfilters, private readonly bool $linked) {}

    public function list(): array
    {
        return $this->spamfilters;
    }

    public function add(Spamfilter $spamfilter): bool
    {
        $this->added[] = $spamfilter;

        return $this->linked;
    }

    public function remove(Spamfilter $spamfilter, string $removedBy): bool
    {
        $this->removed[] = $spamfilter;
        $this->removedBy[] = $removedBy;

        return $this->linked;
    }
}
