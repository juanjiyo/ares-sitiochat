<?php

declare(strict_types=1);

namespace App\Tests\OperServ\Adapter\In\Irc\Command;

use App\OperServ\Adapter\In\Irc\Command\StaffRankCommand;
use App\OperServ\Application\Port\In\OperatorAuthorizationQuery;
use App\OperServ\Application\Port\Out\OperatorAccountLookup;
use App\OperServ\Application\Port\Out\OperatorAssignmentRecord;
use App\OperServ\Application\Port\Out\OperatorAssignmentStore;
use App\OperServ\Application\Port\Out\OperatorRoleRecord;
use App\OperServ\Application\UseCase\ManageIrcop\IrcopOutcome;
use App\OperServ\Application\UseCase\ManageIrcop\ManageIrcop;
use App\OperServ\Application\UseCase\ManageIrcop\ManageIrcopHandlerInterface;
use App\OperServ\Application\UseCase\ManageIrcop\ManageIrcopResult;
use App\OperServ\Application\UseCase\ManageStaff\ManageStaffHandler;
use App\OperServ\Domain\ValueObject\StaffRank;
use DateTimeImmutable;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

use function array_column;

#[CoversClass(StaffRankCommand::class)]
final class StaffRankCommandTest extends TestCase
{
    /** @return iterable<string, array{StaffRank, string, int, string}> */
    public static function ranks(): iterable
    {
        yield 'preoper' => [StaffRank::PREOPER, 'preoper', 1, 'operserv.staff'];
        yield 'oper' => [StaffRank::OPER, 'oper', 2, 'operserv.staff'];
        yield 'admin' => [StaffRank::ADMIN, 'admin', 3, 'operserv.staff.admin'];
        yield 'root' => [StaffRank::ROOT, 'root', 4, 'ROOT'];
    }

    #[Test]
    #[DataProvider('ranks')]
    public function exposesOneCommandPerRankWithItsManagerPermission(StaffRank $rank, string $key, int $order, string $permission): void
    {
        $command = new StaffRankCommand($rank, $this->handler(IrcopOutcome::Added));

        self::assertSame($rank->value, $command->getName());
        self::assertSame([], $command->getAliases());
        self::assertSame(1, $command->getMinArgs());
        self::assertSame("staff.{$key}.syntax", $command->getSyntaxKey());
        self::assertSame("staff.{$key}.help", $command->getHelpKey());
        self::assertSame("staff.{$key}.example", $command->getExampleKey());
        self::assertSame("staff.{$key}.short", $command->getShortDescKey());
        self::assertSame($order, $command->getOrder());
        self::assertSame([], $command->getSubCommandHelp());
        self::assertTrue($command->isOperOnly());
        self::assertSame($permission, $command->getRequiredPermission());
        self::assertSame([], $command->getHelpParams());
    }

    #[Test]
    public function addConfirmsWithTheRankTitle(): void
    {
        $ircops = new StaffCommandIrcops(IrcopOutcome::Added);
        $harness = new ChannelOverrideCommandHarness();

        new StaffRankCommand(StaffRank::OPER, $this->handler(IrcopOutcome::Added, ircops: $ircops))
            ->execute($harness->context('OPER', ['add', 'Pedro'], $this->auth()));

        self::assertSame('OPER', $ircops->commands[0]->roleName);
        self::assertSame(['staff.title.oper', 'staff.added'], array_column($harness->replies, 0));
        self::assertSame('Pedro', $harness->replies[1][1]['%nickname%']);
    }

    /** @return iterable<string, array{list<string>, array<int, string>, IrcopOutcome, list<string>}> */
    public static function replies(): iterable
    {
        yield 'deleted' => [['DEL', 'Pedro'], [10 => 'OPER'], IrcopOutcome::Deleted, ['staff.title.oper', 'staff.deleted']];
        yield 'already other rank' => [['ADD', 'Pedro'], [10 => 'ADMIN'], IrcopOutcome::Added, ['staff.title.admin', 'staff.already_rank']];
        yield 'already legacy staff' => [['ADD', 'Pedro'], [10 => 'HELPER'], IrcopOutcome::Added, ['staff.already_staff']];
        yield 'not this rank' => [['DEL', 'Pedro'], [], IrcopOutcome::Deleted, ['staff.title.oper', 'staff.not_this_rank']];
        yield 'not registered' => [['ADD', 'Nadie'], [], IrcopOutcome::NickNotRegistered, ['error.nick_not_registered']];
        yield 'not active' => [['ADD', 'Nadie'], [], IrcopOutcome::NickNotActive, ['staff.nick_not_active']];
        yield 'rank unavailable' => [['ADD', 'Nadie'], [], IrcopOutcome::RoleNotFound, ['staff.unavailable']];
        yield 'missing nick' => [['ADD'], [], IrcopOutcome::Added, ['staff.oper.syntax', 'error.syntax']];
        yield 'unknown sub' => [['MOVE', 'Pedro'], [], IrcopOutcome::Added, ['staff.oper.syntax', 'error.syntax']];
        yield 'empty list' => [['LIST'], [], IrcopOutcome::Listed, ['staff.title.oper', 'staff.list.empty']];
    }

    /**
     * @param list<string>       $args
     * @param array<int, string> $roles
     * @param list<string>       $keys
     */
    #[Test]
    #[DataProvider('replies')]
    public function repliesWithTheOutcomeMessage(array $args, array $roles, IrcopOutcome $ircop, array $keys): void
    {
        $harness = new ChannelOverrideCommandHarness();

        new StaffRankCommand(StaffRank::OPER, $this->handler($ircop, $roles))->execute($harness->context('OPER', $args, $this->auth()));

        self::assertSame($keys, array_column($harness->replies, 0));
    }

    #[Test]
    public function listsTheRankMembersWithTheirDate(): void
    {
        $harness = new ChannelOverrideCommandHarness();

        new StaffRankCommand(StaffRank::OPER, $this->handler(IrcopOutcome::Listed, [10 => 'OPER']))
            ->execute($harness->context('OPER', ['LIST'], $this->auth()));

        self::assertSame(['staff.title.oper', 'staff.list.header'], array_column($harness->replies, 0));
        self::assertSame('1', $harness->replies[1][1]['%count%']);
        $rows = array_values(array_filter($harness->notices, static fn (string $line): bool => str_contains($line, 'pedro')));
        self::assertCount(1, $rows);
    }

    #[Test]
    public function ignoresCallsWithoutSender(): void
    {
        $ircops = new StaffCommandIrcops(IrcopOutcome::Added);
        $harness = new ChannelOverrideCommandHarness();

        new StaffRankCommand(StaffRank::OPER, $this->handler(IrcopOutcome::Added, ircops: $ircops))
            ->execute($harness->context('OPER', ['ADD', 'Pedro'], $this->auth(), false));

        self::assertSame([], $ircops->commands);
        self::assertSame([], $harness->replies);
    }

    /** @param array<int, string> $roles account id => role (the only account is pedro = 10) */
    private function handler(IrcopOutcome $outcome, array $roles = [], ?StaffCommandIrcops $ircops = null): ManageStaffHandler
    {
        $accounts = $this->createStub(OperatorAccountLookup::class);
        $accounts->method('findIdByNickname')->willReturnCallback(static fn (string $nick): ?int => 'pedro' === strtolower($nick) ? 10 : null);
        $accounts->method('findNicknameById')->willReturn('pedro');

        $assignments = $this->createStub(OperatorAssignmentStore::class);
        $record = static fn (int $id, string $role): OperatorAssignmentRecord => new OperatorAssignmentRecord($id, new OperatorRoleRecord(1, $role, '', false), new DateTimeImmutable('2026-10-01'));
        $assignments->method('findByNickId')->willReturnCallback(static fn (int $id): ?OperatorAssignmentRecord => isset($roles[$id]) ? $record($id, $roles[$id]) : null);
        $assignments->method('all')->willReturn(array_map($record, array_keys($roles), array_values($roles)));

        return new ManageStaffHandler($ircops ?? new StaffCommandIrcops($outcome), $accounts, $assignments);
    }

    private function auth(): OperatorAuthorizationQuery
    {
        return $this->createStub(OperatorAuthorizationQuery::class);
    }
}

final class StaffCommandIrcops implements ManageIrcopHandlerInterface
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
