<?php

declare(strict_types=1);

namespace App\Tests\OperServ\Application\UseCase\Clones;

use App\OperServ\Application\Port\In\Audit\CommandAuditCategory;
use App\OperServ\Application\Port\Out\ConnectedUsersDirectory;
use App\OperServ\Application\Port\Out\KillNetworkUser;
use App\OperServ\Application\Port\Out\NetworkUser;
use App\OperServ\Application\Port\Out\NetworkUserLookup;
use App\OperServ\Application\Port\Out\OperatorAccountLookup;
use App\OperServ\Application\Port\Out\OperatorRoleAccess;
use App\OperServ\Application\Port\Out\RootIdentityRegistry;
use App\OperServ\Application\Security\OperServPermission;
use App\OperServ\Application\Security\ProtectedNetworkTargets;
use App\OperServ\Application\UseCase\Clones\BanCloneHost;
use App\OperServ\Application\UseCase\Clones\BanCloneHostHandler;
use App\OperServ\Application\UseCase\Clones\BanCloneHostOutcome;
use App\OperServ\Application\UseCase\Clones\BanCloneHostResult;
use App\OperServ\Application\UseCase\Clones\CloneHostBan;
use App\OperServ\Application\UseCase\Clones\ConnectionHosts;
use App\OperServ\Application\UseCase\ManageGline\GlineAction;
use App\OperServ\Application\UseCase\ManageGline\ManageGline;
use App\OperServ\Application\UseCase\ManageGline\ManageGlineHandlerInterface;
use App\OperServ\Application\UseCase\ManageGline\ManageGlineOutcome;
use App\OperServ\Application\UseCase\ManageGline\ManageGlineResult;
use App\Tests\OperServ\Application\UseCase\ChannelOverride\RecordingCommandAuditRecorder;
use DateTimeImmutable;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

use function in_array;

#[CoversClass(BanCloneHost::class)]
#[CoversClass(BanCloneHostHandler::class)]
#[CoversClass(BanCloneHostResult::class)]
#[CoversClass(CloneHostBan::class)]
#[CoversClass(ConnectionHosts::class)]
final class BanCloneHostHandlerTest extends TestCase
{
    private const string HOST = '198.51.100.7';

    #[Test]
    public function killClonesGlinesTheTargetHostForOneHourThroughGlineAddAndRecordsAudit(): void
    {
        $at = new DateTimeImmutable('2026-10-29T10:00:00+00:00');
        $audit = new RecordingCommandAuditRecorder();
        $glines = $this->createMock(ManageGlineHandlerInterface::class);
        $glines->expects(self::once())->method('handle')
            ->with(self::callback(static fn (ManageGline $command): bool => GlineAction::Add === $command->action
                && 'Oper' === $command->actor
                && 42 === $command->actorAccountId
                && $at === $command->occurredAt
                && '*@' . self::HOST === $command->mask
                && '1h' === $command->expiry
                && 'Too many clones' === $command->reason))
            ->willReturn(new ManageGlineResult(ManageGlineOutcome::Added, '*@' . self::HOST, expiry: '1h', reason: 'Too many clones'));

        $result = $this->handler($glines, $audit)->handle($this->command(CloneHostBan::KillClones, 'Too many clones', $at));

        self::assertSame(BanCloneHostOutcome::Banned, $result->outcome);
        self::assertSame('Target', $result->nickname);
        self::assertSame(self::HOST, $result->host);
        self::assertSame('*@' . self::HOST, $result->mask);
        self::assertSame('1h', $result->duration);
        self::assertSame('Too many clones', $result->reason);
        self::assertSame(2, $result->sessionCount);
        self::assertCount(1, $audit->records);
        $record = $audit->records[0];
        self::assertSame(CommandAuditCategory::OperatorAction, $record->category);
        self::assertSame('OperServ', $record->service);
        self::assertSame('Oper', $record->actor);
        self::assertSame('KILLCLONES', $record->operation);
        self::assertSame($at, $record->occurredAt);
        self::assertSame('Target', $record->target);
        self::assertSame('Too many clones', $record->reason);
        self::assertSame(OperServPermission::CLONES, $record->permission);
        self::assertSame(self::HOST, $record->targetHost);
        self::assertSame(['mask' => '*@' . self::HOST, 'duration' => '1h', 'sessions' => 2], $record->metadata);
    }

    #[Test]
    public function disconnectsEverySessionOnTheHostAfterTheGlineBecauseAnUdbGlineOnlyStopsNewConnections(): void
    {
        $glines = $this->createStub(ManageGlineHandlerInterface::class);
        $glines->method('handle')->willReturn(new ManageGlineResult(ManageGlineOutcome::Added, '*@' . self::HOST, expiry: '1h', reason: 'Too many clones'));
        $kills = new RecordingKillNetworkUser();

        $result = $this->handler($glines, kills: $kills)->handle($this->command(CloneHostBan::KillClones, 'Too many clones'));

        self::assertSame(BanCloneHostOutcome::Banned, $result->outcome);
        self::assertSame([
            ['U1', 'Killed (OperServ: Oper): Too many clones'],
            ['U2', 'Killed (OperServ: Oper): Too many clones'],
        ], $kills->kills);
    }

    #[Test]
    public function disconnectsNobodyWhenTheGlineIsRefused(): void
    {
        $glines = $this->createStub(ManageGlineHandlerInterface::class);
        $glines->method('handle')->willReturn(new ManageGlineResult(ManageGlineOutcome::AlreadyExists, '*@' . self::HOST));
        $kills = new RecordingKillNetworkUser();

        $this->handler($glines, kills: $kills)->handle($this->command(CloneHostBan::Block, 'x'));

        self::assertSame([], $kills->kills);
    }

    #[Test]
    public function blockGlinesTheTargetHostForFiveHours(): void
    {
        $audit = new RecordingCommandAuditRecorder();
        $glines = $this->createMock(ManageGlineHandlerInterface::class);
        $glines->expects(self::once())->method('handle')
            ->with(self::callback(static fn (ManageGline $command): bool => '5h' === $command->expiry && 'Spam' === $command->reason))
            ->willReturn(new ManageGlineResult(ManageGlineOutcome::Added, '*@' . self::HOST, expiry: '5h', reason: 'Spam'));

        $result = $this->handler($glines, $audit)->handle($this->command(CloneHostBan::Block, 'Spam'));

        self::assertSame(BanCloneHostOutcome::Banned, $result->outcome);
        self::assertSame('5h', $result->duration);
        self::assertSame('BLOCK', $audit->records[0]->operation);
        self::assertSame('5h', $audit->records[0]->metadata['duration']);
    }

    /** @return iterable<string, array{CloneHostBan, string}> */
    public static function defaultReasons(): iterable
    {
        yield 'killclones' => [CloneHostBan::KillClones, 'Clones of Target'];
        yield 'block' => [CloneHostBan::Block, 'Blocked by OperServ'];
    }

    #[Test]
    #[DataProvider('defaultReasons')]
    public function usesADefaultReasonWhenNoneIsGiven(CloneHostBan $ban, string $expectedReason): void
    {
        $glines = $this->createMock(ManageGlineHandlerInterface::class);
        $glines->expects(self::once())->method('handle')
            ->with(self::callback(static fn (ManageGline $command): bool => $expectedReason === $command->reason))
            ->willReturn(new ManageGlineResult(ManageGlineOutcome::Added, '*@' . self::HOST, expiry: $ban->duration(), reason: $expectedReason));

        $result = $this->handler($glines)->handle($this->command($ban, '   '));

        self::assertSame($expectedReason, $result->reason);
    }

    #[Test]
    public function refusesATargetThatIsNotOnline(): void
    {
        $audit = new RecordingCommandAuditRecorder();
        $glines = $this->createMock(ManageGlineHandlerInterface::class);
        $glines->expects(self::never())->method('handle');

        $result = $this->handler($glines, $audit, target: null)->handle($this->command(CloneHostBan::KillClones, 'x'));

        self::assertSame(BanCloneHostOutcome::NotOnline, $result->outcome);
        self::assertSame('target', $result->nickname);
        self::assertSame([], $audit->records);
    }

    /** @return iterable<string, array{string, string}> */
    public static function loopbackHosts(): iterable
    {
        yield 'localhost' => ['localhost', '*'];
        yield 'IPv4 loopback' => ['localhost', '127.0.0.1'];
        yield 'IPv6 loopback' => ['localhost', '::1'];
    }

    #[Test]
    #[DataProvider('loopbackHosts')]
    public function neverGlinesALoopbackHost(string $hostname, string $ipAddress): void
    {
        $audit = new RecordingCommandAuditRecorder();
        $glines = $this->createMock(ManageGlineHandlerInterface::class);
        $glines->expects(self::never())->method('handle');
        $target = new NetworkUser('U1', 'Target', 'tid', $hostname, 'enc', false, false, $ipAddress);

        $result = $this->handler($glines, $audit, target: $target, connected: [$target])->handle($this->command(CloneHostBan::Block, 'x'));

        self::assertSame(BanCloneHostOutcome::LoopbackHost, $result->outcome);
        self::assertSame([], $audit->records);
    }

    /** @return iterable<string, array{list<string>, list<string>, BanCloneHostOutcome, string}> */
    public static function protectedSessions(): iterable
    {
        yield 'root target' => [['Target'], [], BanCloneHostOutcome::ProtectedRoot, 'Target'];
        yield 'root clone' => [['Clone'], [], BanCloneHostOutcome::ProtectedRoot, 'Clone'];
        yield 'ircop clone with role' => [[], ['clone'], BanCloneHostOutcome::ProtectedIrcOperator, 'Clone'];
    }

    /**
     * @param list<string> $roots
     * @param list<string> $operatorsWithRole
     */
    #[Test]
    #[DataProvider('protectedSessions')]
    public function refusesToGlineAHostSharedWithAProtectedUser(array $roots, array $operatorsWithRole, BanCloneHostOutcome $expected, string $protectedNickname): void
    {
        $audit = new RecordingCommandAuditRecorder();
        $glines = $this->createMock(ManageGlineHandlerInterface::class);
        $glines->expects(self::never())->method('handle');

        $result = $this->handler($glines, $audit, roots: $roots, operatorsWithRole: $operatorsWithRole)
            ->handle($this->command(CloneHostBan::KillClones, 'x'));

        self::assertSame($expected, $result->outcome);
        self::assertSame($protectedNickname, $result->protectedNickname);
        self::assertSame([], $audit->records);
    }

    /** @return iterable<string, array{ManageGlineResult, BanCloneHostOutcome}> */
    public static function glineRefusals(): iterable
    {
        yield 'already glined' => [new ManageGlineResult(ManageGlineOutcome::AlreadyExists, '*@' . self::HOST), BanCloneHostOutcome::AlreadyBanned];
        yield 'dangerous mask' => [new ManageGlineResult(ManageGlineOutcome::DangerousMask, '*@' . self::HOST), BanCloneHostOutcome::UnsafeHost];
        yield 'global mask' => [new ManageGlineResult(ManageGlineOutcome::GlobalMask, '*@' . self::HOST), BanCloneHostOutcome::UnsafeHost];
        yield 'invalid mask' => [new ManageGlineResult(ManageGlineOutcome::InvalidMask), BanCloneHostOutcome::UnsafeHost];
        yield 'protected gline subject' => [new ManageGlineResult(ManageGlineOutcome::ProtectedUser, '*@' . self::HOST, protectedNickname: 'Admin'), BanCloneHostOutcome::ProtectedSubject];
        yield 'limit reached' => [new ManageGlineResult(ManageGlineOutcome::LimitReached, limit: 1000), BanCloneHostOutcome::LimitReached];
        yield 'unexpected refusal' => [new ManageGlineResult(ManageGlineOutcome::InvalidExpiry), BanCloneHostOutcome::Failed];
    }

    #[Test]
    #[DataProvider('glineRefusals')]
    public function mapsGlineRefusalsWithoutAuditing(ManageGlineResult $glineResult, BanCloneHostOutcome $expected): void
    {
        $audit = new RecordingCommandAuditRecorder();
        $glines = $this->createStub(ManageGlineHandlerInterface::class);
        $glines->method('handle')->willReturn($glineResult);

        $result = $this->handler($glines, $audit)->handle($this->command(CloneHostBan::KillClones, 'x'));

        self::assertSame($expected, $result->outcome);
        self::assertSame('*@' . self::HOST, $result->mask);
        self::assertSame($glineResult->protectedNickname, $result->protectedNickname);
        self::assertSame($glineResult->limit, $result->limit);
        self::assertSame([], $audit->records);
    }

    private function command(CloneHostBan $ban, string $reason, ?DateTimeImmutable $at = null): BanCloneHost
    {
        return new BanCloneHost($ban, 'Oper', 42, 'OperServ', 'target', $reason, $at ?? new DateTimeImmutable());
    }

    /**
     * @param list<NetworkUser>|null $connected
     * @param list<string>           $roots
     * @param list<string>           $operatorsWithRole lowercase nicknames of identified IRCops with a role
     */
    private function handler(
        ManageGlineHandlerInterface $glines,
        ?RecordingCommandAuditRecorder $audit = null,
        ?NetworkUser $target = new NetworkUser('U1', 'Target', 'tid', 'a.example', 'enc', false, false, self::HOST),
        ?array $connected = null,
        array $roots = [],
        array $operatorsWithRole = [],
        ?RecordingKillNetworkUser $kills = null,
    ): BanCloneHostHandler {
        $connected ??= [
            $target ?? new NetworkUser('U9', 'Nobody', 'n', 'n.example', 'enc', false, false, '203.0.113.9'),
            new NetworkUser('U2', 'Clone', 'cid', 'b.example', 'enc', true, true, self::HOST),
            new NetworkUser('U3', 'Other', 'oid', 'c.example', 'enc', true, true, '203.0.113.9'),
        ];
        $users = $this->createStub(NetworkUserLookup::class);
        $users->method('findByNickname')->willReturn($target);
        $directory = $this->createStub(ConnectedUsersDirectory::class);
        $directory->method('listConnected')->willReturn($connected);
        $rootRegistry = $this->createStub(RootIdentityRegistry::class);
        $rootRegistry->method('contains')->willReturnCallback(static fn (string $nickname): bool => in_array($nickname, $roots, true));
        $accounts = $this->createStub(OperatorAccountLookup::class);
        $accounts->method('findIdByNickname')->willReturnCallback(static fn (string $nickname): ?int => in_array($nickname, $operatorsWithRole, true) ? 7 : null);
        $roles = $this->createStub(OperatorRoleAccess::class);
        $roles->method('hasAssignedRole')->willReturn(true);

        return new BanCloneHostHandler(
            $users,
            new ConnectionHosts($directory),
            new ProtectedNetworkTargets($rootRegistry, $accounts, $roles),
            $glines,
            $kills ?? new RecordingKillNetworkUser(),
            $audit ?? new RecordingCommandAuditRecorder(),
        );
    }
}

final class RecordingKillNetworkUser implements KillNetworkUser
{
    /** @var list<array{string, string}> */
    public array $kills = [];

    public function kill(string $targetUid, string $reason): bool
    {
        $this->kills[] = [$targetUid, $reason];

        return true;
    }
}
