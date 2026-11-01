<?php

declare(strict_types=1);

namespace App\Tests\OperServ\Application\UseCase\ManageStaff;

use App\OperServ\Application\Port\Out\OperatorAccountData;
use App\OperServ\Application\Port\Out\OperatorAccountLookup;
use App\OperServ\Application\Port\Out\OperatorAssignmentRecord;
use App\OperServ\Application\Port\Out\OperatorAssignmentStore;
use App\OperServ\Application\Port\Out\OperatorRoleRecord;
use App\OperServ\Application\UseCase\ManageIrcop\IrcopAction;
use App\OperServ\Application\UseCase\ManageIrcop\IrcopOutcome;
use App\OperServ\Application\UseCase\ManageIrcop\ManageIrcop;
use App\OperServ\Application\UseCase\ManageIrcop\ManageIrcopHandlerInterface;
use App\OperServ\Application\UseCase\ManageIrcop\ManageIrcopResult;
use App\OperServ\Application\UseCase\ManageStaff\ManageStaff;
use App\OperServ\Application\UseCase\ManageStaff\ManageStaffHandler;
use App\OperServ\Application\UseCase\ManageStaff\ManageStaffOutcome;
use App\OperServ\Application\UseCase\ManageStaff\ManageStaffResult;
use App\OperServ\Application\UseCase\ManageStaff\StaffAction;
use App\OperServ\Domain\ValueObject\StaffRank;
use DateTimeImmutable;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(ManageStaffHandler::class)]
#[CoversClass(ManageStaff::class)]
#[CoversClass(ManageStaffResult::class)]
final class ManageStaffHandlerTest extends TestCase
{
    #[Test]
    public function addGivesTheRankRoleThroughTheIrcopUseCase(): void
    {
        $ircops = new StaffTestIrcops(IrcopOutcome::Added);

        $result = $this->handler($ircops)->handle($this->command(StaffRank::OPER, StaffAction::Add, 'Pedro'));

        self::assertSame(ManageStaffOutcome::Added, $result->outcome);
        self::assertSame('Pedro', $result->nickname);
        self::assertSame(StaffRank::OPER, $result->currentRank);
        self::assertCount(1, $ircops->commands);
        $sent = $ircops->commands[0];
        self::assertSame([IrcopAction::Add, 'Pedro', 'OPER', 'Root', 7], [$sent->action, $sent->nickname, $sent->roleName, $sent->actorNickname, $sent->actorAccountId]);
    }

    #[Test]
    public function addRejectsANickThatIsAlreadyStaffWithoutChangingIt(): void
    {
        $ircops = new StaffTestIrcops(IrcopOutcome::Added);
        $handler = $this->handler($ircops, ['pedro' => 10], [10 => 'ADMIN']);

        $result = $handler->handle($this->command(StaffRank::OPER, StaffAction::Add, 'Pedro'));

        self::assertSame(ManageStaffOutcome::AlreadyStaff, $result->outcome);
        self::assertSame(StaffRank::ADMIN, $result->currentRank);
        self::assertSame([], $ircops->commands);
    }

    #[Test]
    public function addReportsALegacyRoleAsStaffWithoutRank(): void
    {
        $result = $this->handler(new StaffTestIrcops(IrcopOutcome::Added), ['pedro' => 10], [10 => 'HELPER'])
            ->handle($this->command(StaffRank::OPER, StaffAction::Add, 'Pedro'));

        self::assertSame(ManageStaffOutcome::AlreadyStaff, $result->outcome);
        self::assertNull($result->currentRank);
    }

    /** @return iterable<string, array{IrcopOutcome, ManageStaffOutcome}> */
    public static function addFailures(): iterable
    {
        yield 'not registered' => [IrcopOutcome::NickNotRegistered, ManageStaffOutcome::NickNotRegistered];
        yield 'not active' => [IrcopOutcome::NickNotActive, ManageStaffOutcome::NickNotActive];
        yield 'rank role not synced yet' => [IrcopOutcome::RoleNotFound, ManageStaffOutcome::RankUnavailable];
    }

    #[Test]
    #[DataProvider('addFailures')]
    public function addMapsTheIrcopFailures(IrcopOutcome $ircop, ManageStaffOutcome $expected): void
    {
        self::assertSame($expected, $this->handler(new StaffTestIrcops($ircop))->handle($this->command(StaffRank::PREOPER, StaffAction::Add, 'Pedro'))->outcome);
    }

    #[Test]
    public function addAndDeleteNeedANick(): void
    {
        $ircops = new StaffTestIrcops(IrcopOutcome::Added);
        $handler = $this->handler($ircops);

        self::assertSame(ManageStaffOutcome::InvalidRequest, $handler->handle($this->command(StaffRank::OPER, StaffAction::Add, ''))->outcome);
        self::assertSame(ManageStaffOutcome::InvalidRequest, $handler->handle($this->command(StaffRank::OPER, StaffAction::Delete, ''))->outcome);
        self::assertSame([], $ircops->commands);
    }

    #[Test]
    public function deleteRemovesOnlyFromTheMatchingRank(): void
    {
        $ircops = new StaffTestIrcops(IrcopOutcome::Deleted);
        $handler = $this->handler($ircops, ['pedro' => 10], [10 => 'OPER']);

        $result = $handler->handle($this->command(StaffRank::OPER, StaffAction::Delete, 'Pedro'));

        self::assertSame(ManageStaffOutcome::Deleted, $result->outcome);
        self::assertSame(StaffRank::OPER, $result->currentRank);
        self::assertSame([IrcopAction::Delete], array_map(static fn (ManageIrcop $c): IrcopAction => $c->action, $ircops->commands));
    }

    #[Test]
    public function deleteRefusesWhenTheNickHasAnotherRankOrNone(): void
    {
        $ircops = new StaffTestIrcops(IrcopOutcome::Deleted);
        $handler = $this->handler($ircops, ['pedro' => 10, 'ana' => 11], [10 => 'ADMIN']);

        $otherRank = $handler->handle($this->command(StaffRank::OPER, StaffAction::Delete, 'Pedro'));
        $notStaff = $handler->handle($this->command(StaffRank::OPER, StaffAction::Delete, 'Ana'));
        $unknown = $handler->handle($this->command(StaffRank::OPER, StaffAction::Delete, 'Nobody'));

        self::assertSame(ManageStaffOutcome::NotThisRank, $otherRank->outcome);
        self::assertSame(StaffRank::ADMIN, $otherRank->currentRank);
        self::assertSame(ManageStaffOutcome::NotThisRank, $notStaff->outcome);
        self::assertNull($notStaff->currentRank);
        self::assertSame(ManageStaffOutcome::NotThisRank, $unknown->outcome);
        self::assertSame([], $ircops->commands);
    }

    #[Test]
    public function listShowsOnlyTheRequestedRank(): void
    {
        $handler = $this->handler(new StaffTestIrcops(IrcopOutcome::Listed), ['pedro' => 10, 'ana' => 11, 'luis' => 12], [10 => 'OPER', 11 => 'ADMIN', 12 => 'OPER', 99 => 'OPER']);

        $result = $handler->handle($this->command(StaffRank::OPER, StaffAction::List));

        self::assertSame(ManageStaffOutcome::Listed, $result->outcome);
        self::assertSame(['pedro', 'luis', '99'], array_map(static fn ($e): string => $e->nickname, $result->entries));
        self::assertSame(StaffRank::OPER, $result->currentRank);
    }

    #[Test]
    public function unknownActionIsReported(): void
    {
        self::assertSame(ManageStaffOutcome::UnknownAction, $this->handler(new StaffTestIrcops(IrcopOutcome::Added))->handle($this->command(StaffRank::OPER, StaffAction::Unknown))->outcome);
    }

    private function command(StaffRank $rank, StaffAction $action, string $nick = ''): ManageStaff
    {
        return new ManageStaff($rank, $action, 'Root', 7, new DateTimeImmutable('2026-11-01 12:00:00'), $nick);
    }

    /**
     * @param array<string, int> $accounts lowercase nick => account id
     * @param array<int, string> $roles    account id => role name
     */
    private function handler(StaffTestIrcops $ircops, array $accounts = [], array $roles = []): ManageStaffHandler
    {
        return new ManageStaffHandler($ircops, new StaffTestAccounts($accounts), new StaffTestAssignments($roles));
    }
}

final class StaffTestIrcops implements ManageIrcopHandlerInterface
{
    /** @var list<ManageIrcop> */
    public array $commands = [];

    public function __construct(private readonly IrcopOutcome $outcome) {}

    public function handle(ManageIrcop $command): ManageIrcopResult
    {
        $this->commands[] = $command;

        return new ManageIrcopResult($this->outcome, $command->nickname, $command->roleName);
    }
}

final readonly class StaffTestAccounts implements OperatorAccountLookup
{
    /** @param array<string, int> $accounts */
    public function __construct(private array $accounts) {}

    public function findIdByNickname(string $nickname): ?int
    {
        return $this->accounts[strtolower($nickname)] ?? null;
    }

    public function findByNickname(string $nickname): ?OperatorAccountData
    {
        return null;
    }

    public function findNicknameById(int $id): ?string
    {
        $nick = array_search($id, $this->accounts, true);

        return false === $nick ? null : $nick;
    }
}

final readonly class StaffTestAssignments implements OperatorAssignmentStore
{
    /** @param array<int, string> $roles */
    public function __construct(private array $roles) {}

    public function findByNickId(int $nickId): ?OperatorAssignmentRecord
    {
        return isset($this->roles[$nickId]) ? $this->record($nickId, $this->roles[$nickId]) : null;
    }

    public function all(): array
    {
        $all = [];
        foreach ($this->roles as $nickId => $role) {
            $all[] = $this->record($nickId, $role);
        }

        return $all;
    }

    public function assign(int $nickId, OperatorRoleRecord $role, ?int $addedById, DateTimeImmutable $addedAt): void {}

    public function remove(int $nickId): void {}

    private function record(int $nickId, string $role): OperatorAssignmentRecord
    {
        return new OperatorAssignmentRecord($nickId, new OperatorRoleRecord(1, $role, '', false), new DateTimeImmutable('2026-10-01'));
    }
}
