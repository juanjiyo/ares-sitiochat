<?php

declare(strict_types=1);

namespace App\Tests\OperServ\Application\UseCase\ManageEline;

use App\OperServ\Application\Port\Out\ElineEntry;
use App\OperServ\Application\Security\OperServPermission;
use App\OperServ\Application\UseCase\ManageEline\ElineAction;
use App\OperServ\Application\UseCase\ManageEline\ManageEline;
use App\OperServ\Application\UseCase\ManageEline\ManageElineHandler;
use App\OperServ\Application\UseCase\ManageEline\ManageElineOutcome;
use App\OperServ\Application\UseCase\ManageEline\ManageElineResult;
use App\Tests\OperServ\Application\UseCase\ChannelOverride\RecordingCommandAuditRecorder;
use DateTimeImmutable;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

use function array_map;

#[CoversClass(ManageElineHandler::class)]
#[CoversClass(ManageEline::class)]
#[CoversClass(ManageElineResult::class)]
final class ManageElineHandlerTest extends TestCase
{
    private InMemoryElineRepository $elines;

    private RecordingElineNetwork $network;

    private RecordingCommandAuditRecorder $audit;

    private DateTimeImmutable $now;

    protected function setUp(): void
    {
        $this->elines = new InMemoryElineRepository();
        $this->network = new RecordingElineNetwork();
        $this->audit = new RecordingCommandAuditRecorder();
        $this->now = new DateTimeImmutable('2026-10-30 12:00:00');
    }

    private function handle(ElineAction $action, string $target = '', string $reason = '', ?string $expiry = null): ManageElineResult
    {
        return new ManageElineHandler($this->elines, $this->network, $this->audit)
            ->handle(new ManageEline($action, 'Oper', $this->now, $target, $reason, $expiry));
    }

    /** @return iterable<string, array{string, ?string, string, ?string}> */
    public static function validAdds(): iterable
    {
        yield 'bare IPv4' => ['203.0.113.10', null, '*@203.0.113.10', null];
        yield 'user and IP, 30 days' => ['znc@203.0.113.10', '+30d', 'znc@203.0.113.10', '2026-11-29 12:00:00'];
        yield 'CIDR range, 12 hours' => ['198.51.100.0/24', '+12h', '*@198.51.100.0/24', '2026-10-31 00:00:00'];
        yield 'IPv6 range, permanent' => ['2001:DB8::/32', '+0', '*@2001:db8::/32', null];
    }

    #[Test]
    #[DataProvider('validAdds')]
    public function addsAnElineAndPublishesIt(string $mask, ?string $expiry, string $stored, ?string $expiresAt): void
    {
        $result = $this->handle(ElineAction::Add, $mask, 'ZNC IRCops', $expiry);

        self::assertSame(ManageElineOutcome::Added, $result->outcome);
        self::assertSame($stored, $result->entries[0]->mask);
        self::assertSame($expiresAt, $result->entries[0]->expiresAt?->format('Y-m-d H:i:s'));
        self::assertSame(['Oper', 'ZNC IRCops'], [$this->elines->entries[$stored]->setBy, $this->elines->entries[$stored]->reason]);
        self::assertSame([['add', $stored]], $this->network->calls);
        self::assertSame(['ELINE ADD', $stored, OperServPermission::ELINE], [$this->audit->records[0]->operation, $this->audit->records[0]->target, $this->audit->records[0]->permission]);
    }

    /** @return iterable<string, array{string, ?string, ManageElineOutcome}> */
    public static function invalidAdds(): iterable
    {
        yield 'hostname' => ['znc.example.org', null, ManageElineOutcome::InvalidMask];
        yield 'bad user part' => ['a b@1.2.3.4', null, ManageElineOutcome::InvalidMask];
        yield 'bad prefix' => ['1.2.3.0/33', null, ManageElineOutcome::InvalidMask];
        yield 'non numeric prefix' => ['1.2.3.0/x', null, ManageElineOutcome::InvalidMask];
        yield 'bad expiry' => ['1.2.3.4', '+mañana', ManageElineOutcome::InvalidExpiry];
    }

    #[Test]
    #[DataProvider('invalidAdds')]
    public function refusesInvalidMasksAndExpiries(string $mask, ?string $expiry, ManageElineOutcome $outcome): void
    {
        self::assertSame($outcome, $this->handle(ElineAction::Add, $mask, 'r', $expiry)->outcome);
        self::assertSame([], $this->elines->entries);
        self::assertSame([], $this->network->calls);
    }

    #[Test]
    public function refusesDuplicatesAndAddsNothingWithoutANetwork(): void
    {
        $this->handle(ElineAction::Add, '1.2.3.4', 'r');
        self::assertSame(ManageElineOutcome::AlreadyExists, $this->handle(ElineAction::Add, '*@1.2.3.4', 'r')->outcome);

        $this->network->up = false;
        self::assertSame(ManageElineOutcome::NetworkUnavailable, $this->handle(ElineAction::Add, '5.6.7.8', 'r')->outcome);
        self::assertNull($this->elines->findByMask('*@5.6.7.8'));
    }

    #[Test]
    public function deletesByMaskNumberOrList(): void
    {
        foreach (['1.1.1.1', '2.2.2.2', '3.3.3.3', '4.4.4.4', '5.5.5.5'] as $index => $ip) {
            $this->elines->save(new ElineEntry('*@' . $ip, 'Oper', 'r', $this->now->modify('-' . (10 - $index) . ' minutes'), null));
        }

        $masks = static fn (ManageElineResult $result): array => array_map(static fn (ElineEntry $entry): string => $entry->mask, $result->entries);

        self::assertSame(['*@1.1.1.1', '*@3.3.3.3', '*@4.4.4.4'], $masks($this->handle(ElineAction::Delete, '1,3-4,9')));
        self::assertSame(['*@5.5.5.5'], $masks($this->handle(ElineAction::Delete, '5.5.5.5')));
        self::assertSame(['*@2.2.2.2'], $masks($this->handle(ElineAction::Delete, '1')));
        self::assertSame(ManageElineOutcome::NotFound, $this->handle(ElineAction::Delete, '7')->outcome);
        self::assertSame(ManageElineOutcome::NotFound, $this->handle(ElineAction::Delete, 'nada')->outcome);
        self::assertSame([], $this->elines->entries);
        self::assertSame(['remove', '*@1.1.1.1 by Oper'], $this->network->calls[0]);
        self::assertCount(5, $this->audit->records);
    }

    #[Test]
    public function listsAndCountsOnlyActiveElinesAndForgetsExpiredOnes(): void
    {
        $this->elines->save(new ElineEntry('*@1.1.1.1', 'Oper', 'r', $this->now, null));
        $this->elines->save(new ElineEntry('*@2.2.2.2', 'Oper', 'r', $this->now, $this->now->modify('-1 second')));

        self::assertSame(ManageElineOutcome::Listed, ($list = $this->handle(ElineAction::List))->outcome);
        self::assertCount(1, $list->entries);
        self::assertSame(ManageElineOutcome::Counted, $this->handle(ElineAction::Count)->outcome);
        self::assertSame(['*@1.1.1.1'], array_keys($this->elines->entries));
    }

    #[Test]
    public function republishesActiveElinesOnResync(): void
    {
        $this->elines->save(new ElineEntry('*@1.1.1.1', 'Oper', 'r', $this->now, null));
        $this->elines->save(new ElineEntry('*@2.2.2.2', 'Oper', 'r', $this->now, $this->now->modify('-1 second')));

        new ManageElineHandler($this->elines, $this->network, $this->audit)->resync($this->now);

        self::assertSame([['add', '*@1.1.1.1']], $this->network->calls);
    }
}
