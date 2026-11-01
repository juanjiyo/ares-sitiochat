<?php

declare(strict_types=1);

namespace App\Tests\OperServ\Application\UseCase\ManageSpamfilter;

use App\OperServ\Application\Port\In\Audit\CommandAuditCategory;
use App\OperServ\Application\Port\In\Audit\CommandAuditRecord;
use App\OperServ\Application\Port\In\CommandAuditRecorder;
use App\OperServ\Application\Port\Out\NetworkSpamfilters;
use App\OperServ\Application\Port\Out\SpamfilterEntry;
use App\OperServ\Application\UseCase\ManageSpamfilter\ManageSpamfilter;
use App\OperServ\Application\UseCase\ManageSpamfilter\ManageSpamfilterHandler;
use App\OperServ\Application\UseCase\ManageSpamfilter\ManageSpamfilterOutcome;
use App\OperServ\Application\UseCase\ManageSpamfilter\ManageSpamfilterResult;
use App\OperServ\Application\UseCase\ManageSpamfilter\SpamfilterAction;
use DateTimeImmutable;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(ManageSpamfilterHandler::class)]
#[CoversClass(ManageSpamfilter::class)]
#[CoversClass(ManageSpamfilterResult::class)]
final class ManageSpamfilterHandlerTest extends TestCase
{
    private DateTimeImmutable $now;

    protected function setUp(): void
    {
        $this->now = new DateTimeImmutable('2026-11-01 12:00:00');
    }

    #[Test]
    public function addsASimpleSpamfilterWithTheDefaultBanDurationAndAuditsIt(): void
    {
        $network = new FakeNetworkSpamfilters();
        $audit = new RecordingSpamfilterAudit();

        $result = $this->handler($network, $audit)->handle($this->add('Npc', 'GLINE', '*casino*', 'Casino spam'));

        self::assertSame(ManageSpamfilterOutcome::Added, $result->outcome);
        $expected = new SpamfilterEntry('cpN', 'g', 'simple', '*casino*', 86400, 'Casino spam', 'Oper');
        self::assertSame((array) $expected, (array) $result->entry);
        self::assertSame([(array) $expected], array_map(static fn (SpamfilterEntry $e): array => (array) $e, $network->added));
        self::assertCount(1, $audit->records);
        $record = $audit->records[0];
        self::assertSame(CommandAuditCategory::OperatorAction, $record->category);
        self::assertSame('SPAMFILTER ADD', $record->operation);
        self::assertSame('Oper', $record->actor);
        self::assertSame('*casino*', $record->target);
        self::assertSame('Casino spam', $record->reason);
        self::assertSame('operserv.spamfilter', $record->permission);
        self::assertSame(['targets' => 'cpN', 'action' => 'gline', 'match_type' => 'simple', 'ban_duration' => 86400], $record->metadata);
    }

    #[Test]
    public function addsARegexSpamfilterWithACustomDuration(): void
    {
        $network = new FakeNetworkSpamfilters();

        $result = $this->handler($network)->handle($this->add('ALL', 'warn', 'casino[0-9]+', 'r', regex: true, duration: '2h'));

        self::assertSame(ManageSpamfilterOutcome::Added, $result->outcome);
        self::assertSame('cpnNPqat', $result->entry?->targets);
        self::assertSame('w', $result->entry->action);
        self::assertSame('regex', $result->entry->matchType);
        self::assertSame(7200, $result->entry->banDuration);
    }

    /** @return iterable<string, array{ManageSpamfilterOutcome, string, string, string, string, bool, ?string}> */
    public static function rejectedAdds(): iterable
    {
        yield 'missing targets' => [ManageSpamfilterOutcome::InvalidRequest, '', 'warn', '*x*', 'r', false, null];
        yield 'missing action' => [ManageSpamfilterOutcome::InvalidRequest, 'c', '', '*x*', 'r', false, null];
        yield 'missing pattern' => [ManageSpamfilterOutcome::InvalidRequest, 'c', 'warn', '', 'r', false, null];
        yield 'missing reason' => [ManageSpamfilterOutcome::InvalidRequest, 'c', 'warn', '*x*', '', false, null];
        yield 'unknown target' => [ManageSpamfilterOutcome::InvalidTargets, 'cx', 'warn', '*x*', 'r', false, null];
        yield 'unknown action' => [ManageSpamfilterOutcome::InvalidAction, 'c', 'nuke', '*x*', 'r', false, null];
        yield 'broken regex' => [ManageSpamfilterOutcome::InvalidRegex, 'c', 'warn', 'casino(', 'r', true, null];
        yield 'bad duration' => [ManageSpamfilterOutcome::InvalidDuration, 'c', 'gline', '*x*', 'r', false, 'soon'];
        yield 'permanent duration' => [ManageSpamfilterOutcome::InvalidDuration, 'c', 'gline', '*x*', 'r', false, '0'];
    }

    #[Test]
    #[DataProvider('rejectedAdds')]
    public function rejectsInvalidAddsWithoutTouchingTheNetwork(ManageSpamfilterOutcome $outcome, string $targets, string $action, string $pattern, string $reason, bool $regex, ?string $duration): void
    {
        $network = new FakeNetworkSpamfilters();
        $audit = new RecordingSpamfilterAudit();

        $result = $this->handler($network, $audit)->handle($this->add($targets, $action, $pattern, $reason, $regex, $duration));

        self::assertSame($outcome, $result->outcome);
        self::assertSame([], $network->added);
        self::assertSame([], $audit->records);
    }

    #[Test]
    public function regexWithSlashesIsAccepted(): void
    {
        $result = $this->handler(new FakeNetworkSpamfilters())->handle($this->add('c', 'warn', 'https?://spam/x', 'r', regex: true));

        self::assertSame(ManageSpamfilterOutcome::Added, $result->outcome);
    }

    #[Test]
    public function refusesADuplicateOfTheSameIdentity(): void
    {
        $existing = new SpamfilterEntry('cpN', 'g', 'simple', '*casino*', 86400, 'old', 'Other');
        $network = new FakeNetworkSpamfilters([$existing]);

        $result = $this->handler($network)->handle($this->add('cNp', 'gline', '*casino*', 'again'));

        self::assertSame(ManageSpamfilterOutcome::AlreadyExists, $result->outcome);
        self::assertSame($existing, $result->entry);
        self::assertSame([], $network->added);
    }

    #[Test]
    public function samePatternWithAnotherActionIsADifferentSpamfilter(): void
    {
        $network = new FakeNetworkSpamfilters([new SpamfilterEntry('c', 'w', 'simple', '*casino*', 86400, 'old', 'Other')]);

        $result = $this->handler($network)->handle($this->add('c', 'block', '*casino*', 'r'));

        self::assertSame(ManageSpamfilterOutcome::Added, $result->outcome);
    }

    #[Test]
    public function reportsUnavailableWhenTheIrcdIsNotLinkedAndDoesNotAudit(): void
    {
        $network = new FakeNetworkSpamfilters([], false);
        $audit = new RecordingSpamfilterAudit();

        self::assertSame(ManageSpamfilterOutcome::Unavailable, $this->handler($network, $audit)->handle($this->add('c', 'warn', '*x*', 'r'))->outcome);
        self::assertSame([], $audit->records);
    }

    #[Test]
    public function deletesByNumberOrExactPatternNamingTheOperAndAudits(): void
    {
        $first = new SpamfilterEntry('c', 'w', 'simple', '*casino*', 0, 'r1', 'A');
        $second = new SpamfilterEntry('p', 'b', 'simple', '*free*', 0, 'r2', 'B');
        $network = new FakeNetworkSpamfilters([$first, $second]);
        $audit = new RecordingSpamfilterAudit();
        $handler = $this->handler($network, $audit);

        $byNumber = $handler->handle(new ManageSpamfilter(SpamfilterAction::Delete, 'Oper', $this->now, item: '2'));
        $byPattern = $handler->handle(new ManageSpamfilter(SpamfilterAction::Delete, 'Oper', $this->now, item: '*casino*'));

        self::assertSame(ManageSpamfilterOutcome::Deleted, $byNumber->outcome);
        self::assertSame($second, $byNumber->entry);
        self::assertSame(ManageSpamfilterOutcome::Deleted, $byPattern->outcome);
        self::assertSame($first, $byPattern->entry);
        self::assertSame([[$second, 'Oper'], [$first, 'Oper']], $network->removed);
        self::assertSame(['SPAMFILTER DEL', 'SPAMFILTER DEL'], array_map(static fn (CommandAuditRecord $r): string => $r->operation, $audit->records));
        self::assertSame('*free*', $audit->records[0]->target);
    }

    #[Test]
    public function deleteReportsMissingItemsAndRequests(): void
    {
        $network = new FakeNetworkSpamfilters([new SpamfilterEntry('c', 'w', 'simple', '*casino*', 0, 'r', 'A')]);
        $handler = $this->handler($network);

        self::assertSame(ManageSpamfilterOutcome::InvalidRequest, $handler->handle(new ManageSpamfilter(SpamfilterAction::Delete, 'Oper', $this->now))->outcome);
        foreach (['0', '2', '*CASINO*', '*cas*'] as $item) {
            $result = $handler->handle(new ManageSpamfilter(SpamfilterAction::Delete, 'Oper', $this->now, item: $item));
            self::assertSame(ManageSpamfilterOutcome::NotFound, $result->outcome, $item);
            self::assertSame($item, $result->item);
        }
        self::assertSame([], $network->removed);
    }

    #[Test]
    public function deleteReportsUnavailableWithoutLink(): void
    {
        $audit = new RecordingSpamfilterAudit();
        $network = new FakeNetworkSpamfilters([new SpamfilterEntry('c', 'w', 'simple', '*casino*', 0, 'r', 'A')], false);

        $result = $this->handler($network, $audit)->handle(new ManageSpamfilter(SpamfilterAction::Delete, 'Oper', $this->now, item: '1'));

        self::assertSame(ManageSpamfilterOutcome::Unavailable, $result->outcome);
        self::assertSame([], $audit->records);
    }

    #[Test]
    public function listKeepsTheRealNumbersWhenFiltering(): void
    {
        $first = new SpamfilterEntry('c', 'w', 'simple', '*casino*', 0, 'r', 'A');
        $second = new SpamfilterEntry('p', 'b', 'simple', '*free*', 0, 'r', 'B');
        $third = new SpamfilterEntry('p', 'b', 'regex', 'Casino[0-9]+', 0, 'r', 'B');
        $handler = $this->handler(new FakeNetworkSpamfilters([$first, $second, $third]));

        $all = $handler->handle(new ManageSpamfilter(SpamfilterAction::List, 'Oper', $this->now));
        $filtered = $handler->handle(new ManageSpamfilter(SpamfilterAction::List, 'Oper', $this->now, listFilter: 'CASINO'));

        self::assertSame(ManageSpamfilterOutcome::Listed, $all->outcome);
        self::assertSame([1 => $first, 2 => $second, 3 => $third], $all->entries);
        self::assertSame([1 => $first, 3 => $third], $filtered->entries);
    }

    #[Test]
    public function listReportsEmptyWhenNothingMatches(): void
    {
        $handler = $this->handler(new FakeNetworkSpamfilters([new SpamfilterEntry('c', 'w', 'simple', '*casino*', 0, 'r', 'A')]));

        self::assertSame(ManageSpamfilterOutcome::ListEmpty, $this->handler(new FakeNetworkSpamfilters())->handle(new ManageSpamfilter(SpamfilterAction::List, 'Oper', $this->now))->outcome);
        self::assertSame(ManageSpamfilterOutcome::ListEmpty, $handler->handle(new ManageSpamfilter(SpamfilterAction::List, 'Oper', $this->now, listFilter: 'free'))->outcome);
    }

    #[Test]
    public function unknownActionIsReported(): void
    {
        self::assertSame(ManageSpamfilterOutcome::UnknownAction, $this->handler(new FakeNetworkSpamfilters())->handle(new ManageSpamfilter(SpamfilterAction::Unknown, 'Oper', $this->now))->outcome);
    }

    private function add(string $targets, string $action, string $pattern, string $reason, bool $regex = false, ?string $duration = null): ManageSpamfilter
    {
        return new ManageSpamfilter(SpamfilterAction::Add, 'Oper', $this->now, $targets, $action, $pattern, $reason, $regex, $duration);
    }

    private function handler(FakeNetworkSpamfilters $network, ?RecordingSpamfilterAudit $audit = null): ManageSpamfilterHandler
    {
        return new ManageSpamfilterHandler($network, $audit ?? new RecordingSpamfilterAudit());
    }
}

final class FakeNetworkSpamfilters implements NetworkSpamfilters
{
    /** @var list<SpamfilterEntry> */
    public array $added = [];

    /** @var list<array{SpamfilterEntry, string}> */
    public array $removed = [];

    /** @param list<SpamfilterEntry> $entries */
    public function __construct(private readonly array $entries = [], private readonly bool $linked = true) {}

    public function all(): array
    {
        return $this->entries;
    }

    public function add(SpamfilterEntry $entry): bool
    {
        if ($this->linked) {
            $this->added[] = $entry;
        }

        return $this->linked;
    }

    public function remove(SpamfilterEntry $entry, string $removedBy): bool
    {
        if ($this->linked) {
            $this->removed[] = [$entry, $removedBy];
        }

        return $this->linked;
    }
}

final class RecordingSpamfilterAudit implements CommandAuditRecorder
{
    /** @var list<CommandAuditRecord> */
    public array $records = [];

    public function record(CommandAuditRecord $record): void
    {
        $this->records[] = $record;
    }
}
