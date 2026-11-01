<?php

declare(strict_types=1);

namespace App\Tests\Irc\Adapter\Network;

use App\Irc\Adapter\Network\InMemorySpamfilterRegistry;
use App\Irc\Application\Port\In\ActiveConnectionHolderInterface;
use App\Irc\Application\Port\In\Spamfilter;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Clock\MockClock;

#[CoversClass(InMemorySpamfilterRegistry::class)]
#[CoversClass(Spamfilter::class)]
final class InMemorySpamfilterRegistryTest extends TestCase
{
    #[Test]
    public function listsInArrivalOrderAndReplacesTheSameIdentity(): void
    {
        $registry = $this->registry(new SpamfilterTestConnection());
        $first = $this->spamfilter('*casino*', 'cpn', 'g');
        $second = $this->spamfilter('*free*', 'c', 'w');
        $sameMatchOtherAction = $this->spamfilter('*casino*', 'cpn', 'b');
        $registry->record($first);
        $registry->record($second);
        $registry->record($sameMatchOtherAction);
        $replacement = new Spamfilter('cpn', 'g', 'simple', '*casino*', 60, 'again', 'Other');
        $registry->record($replacement);

        self::assertSame([$replacement, $second, $sameMatchOtherAction], $registry->list());
    }

    #[Test]
    public function forgetsByIdentityAndClearsEverything(): void
    {
        $registry = $this->registry(new SpamfilterTestConnection());
        $registry->record($this->spamfilter('*casino*', 'cpn', 'g'));
        $registry->record($this->spamfilter('*free*', 'c', 'w'));

        $registry->forget('cpn', 'b', '*casino*');
        self::assertCount(2, $registry->list());
        $registry->forget('cpn', 'g', '*casino*');
        self::assertCount(1, $registry->list());

        $registry->clear();
        self::assertSame([], $registry->list());
    }

    #[Test]
    public function addSendsTheTklAndRecordsIt(): void
    {
        $connection = new SpamfilterTestConnection('001');
        $registry = $this->registry($connection);
        $spamfilter = new Spamfilter('cpn', 'g', 'simple', '*free money*', 86400, 'No spam here', 'JuanJo');

        self::assertTrue($registry->add($spamfilter));

        self::assertSame([':001 TKL + F cpn g JuanJo 0 1793534400 86400 No_spam_here simple :*free money*'], $connection->lines);
        self::assertSame([$spamfilter], $registry->list());
    }

    #[Test]
    public function removeSendsTheTklNamingTheRemoverAndForgetsIt(): void
    {
        $connection = new SpamfilterTestConnection('001');
        $registry = $this->registry($connection);
        $spamfilter = new Spamfilter('c', 'w', 'regex', 'casino[0-9]+', 0, 'Casino spam', 'Oper');
        $registry->record($spamfilter);

        self::assertTrue($registry->remove($spamfilter, 'JuanJo'));

        self::assertSame([':001 TKL - F c w JuanJo 0 1793534400 0 Casino_spam regex :casino[0-9]+'], $connection->lines);
        self::assertSame([], $registry->list());
    }

    #[Test]
    public function withoutLinkNothingIsSentNorChanged(): void
    {
        foreach ([new SpamfilterTestConnection(null), new SpamfilterTestConnection('001', false)] as $connection) {
            $registry = $this->registry($connection);
            $kept = $this->spamfilter('*casino*', 'cpn', 'g');
            $registry->record($kept);

            self::assertFalse($registry->add($this->spamfilter('*free*', 'c', 'w')));
            self::assertFalse($registry->remove($kept, 'JuanJo'));

            self::assertSame([], $connection->lines);
            self::assertSame([$kept], $registry->list());
        }
    }

    private function registry(SpamfilterTestConnection $connection): InMemorySpamfilterRegistry
    {
        return new InMemorySpamfilterRegistry($connection, new MockClock('2026-11-01 12:00:00 UTC'));
    }

    private function spamfilter(string $match, string $targets, string $action): Spamfilter
    {
        return new Spamfilter($targets, $action, 'simple', $match, 86400, 'Spam', 'Oper');
    }
}

final class SpamfilterTestConnection implements ActiveConnectionHolderInterface
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
