<?php

declare(strict_types=1);

namespace App\Tests\OperServ\Adapter\In\Irc\Command;

use App\OperServ\Adapter\In\Irc\Command\UmodeCommand;
use App\OperServ\Application\Port\In\OperatorAuthorizationQuery;
use App\OperServ\Application\Port\Out\NetworkUser;
use App\OperServ\Application\Port\Out\NetworkUserLookup;
use App\OperServ\Application\Port\Out\OperatorAccountLookup;
use App\OperServ\Application\Port\Out\OperatorRoleAccess;
use App\OperServ\Application\Port\Out\OperatorRoleRecord;
use App\OperServ\Application\Port\Out\OperatorRoleStore;
use App\OperServ\Application\Port\Out\RootIdentityRegistry;
use App\OperServ\Application\Port\Out\UserModeNetworkActions;
use App\OperServ\Application\Security\OperServPermission;
use App\OperServ\Application\Security\ProtectedNetworkTargets;
use App\OperServ\Application\UseCase\UserMode\ChangeUserModes;
use App\OperServ\Application\UseCase\UserMode\ChangeUserModesHandler;
use App\OperServ\Application\UseCase\UserMode\UserModeResult;
use App\Tests\OperServ\Application\UseCase\ChannelOverride\RecordingCommandAuditRecorder;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

use function in_array;

#[CoversClass(UmodeCommand::class)]
#[CoversClass(ChangeUserModes::class)]
#[CoversClass(ChangeUserModesHandler::class)]
#[CoversClass(UserModeResult::class)]
final class UmodeCommandTest extends TestCase
{
    /** @var list<array{string, string}> */
    private array $applied = [];

    private RecordingCommandAuditRecorder $audit;

    protected function setUp(): void
    {
        $this->audit = new RecordingCommandAuditRecorder();
    }

    #[Test]
    public function exposesOperOnlyPermissionAndHelpMetadata(): void
    {
        $command = new UmodeCommand($this->handler());

        self::assertSame('UMODE', $command->getName());
        self::assertSame([], $command->getAliases());
        self::assertSame(2, $command->getMinArgs());
        self::assertSame('umode.syntax', $command->getSyntaxKey());
        self::assertSame('umode.help', $command->getHelpKey());
        self::assertSame('umode.example', $command->getExampleKey());
        self::assertSame(23, $command->getOrder());
        self::assertSame('umode.short', $command->getShortDescKey());
        self::assertSame([], $command->getSubCommandHelp());
        self::assertTrue($command->isOperOnly());
        self::assertSame(OperServPermission::UMODE, $command->getRequiredPermission());
        self::assertSame([], $command->getHelpParams());
    }

    #[Test]
    public function appliesModesWithinTheActorRankAndAuditsThem(): void
    {
        $harness = $this->run_('Target', '+o-w', operclass: 'netadmin');

        self::assertSame([['UID1', '+o-w']], $this->applied);
        self::assertSame([['umode.done', ['%nickname%' => 'Target', '%modes%' => '+o-w']]], self::replies($harness));
        self::assertSame('UMODE', $this->audit->records[0]->operation);
        self::assertSame('Oper', $this->audit->records[0]->actor);
        self::assertSame(OperServPermission::UMODE, $this->audit->records[0]->permission);
        self::assertSame(['modes' => '+o-w'], $this->audit->records[0]->metadata);
    }

    /** @return iterable<string, array{?string, string, string}> */
    public static function modesAboveRank(): iterable
    {
        yield 'IRCop without role cannot use operator modes' => [null, '+is', 's'];
        yield 'pre-operador cannot use operator modes' => ['locop', '+W', 'W'];
        yield 'operador cannot use administrator modes' => ['globop', '+sW-H', 'H'];
        yield 'administrator cannot use root modes, each letter once' => ['admin', '+SS-z', 'Sz'];
    }

    #[Test]
    #[DataProvider('modesAboveRank')]
    public function refusesModesAboveTheActorRank(?string $operclass, string $modes, string $above): void
    {
        $harness = $this->run_('Target', $modes, operclass: $operclass);

        self::assertSame([], $this->applied);
        self::assertSame([['umode.above_rank', ['%modes%' => $above]]], self::replies($harness));
    }

    #[Test]
    public function rootMayUseAnyModeEvenOnProtectedUsers(): void
    {
        $harness = $this->run_('Target', '+Sz', roots: ['Oper', 'Target']);

        self::assertSame([['UID1', '+Sz']], $this->applied);
        self::assertSame('umode.done', self::replies($harness)[0][0]);
    }

    #[Test]
    public function anIrcopMayChangeTheirOwnModesWithinTheirRank(): void
    {
        $harness = $this->run_('oper', '+W', operclass: 'globop', targetIsRoleIrcop: true, targetNick: 'Oper');

        self::assertSame([['UID1', '+W']], $this->applied);
        self::assertSame('umode.done', self::replies($harness)[0][0]);
    }

    /** @return iterable<string, array{string, string, array<string, string>}> */
    public static function refusals(): iterable
    {
        yield 'no sign' => ['abc', 'umode.invalid_modes', ['%modes%' => 'abc']];
        yield 'sign only' => ['+', 'umode.invalid_modes', ['%modes%' => '+']];
        yield 'not letters' => ['+o1', 'umode.invalid_modes', ['%modes%' => '+o1']];
        yield 'registered' => ['+r', 'umode.forbidden', []];
        yield 'helper' => ['-ih', 'umode.forbidden', []];
    }

    /** @param array<string, string> $params */
    #[Test]
    #[DataProvider('refusals')]
    public function refusesMalformedOrForbiddenModes(string $modes, string $key, array $params): void
    {
        $harness = $this->run_('Target', $modes, roots: ['Oper']);

        self::assertSame([], $this->applied);
        self::assertSame([[$key, $params]], self::replies($harness));
    }

    #[Test]
    public function reportsOfflineAndProtectedTargets(): void
    {
        self::assertSame([['channel.user_not_online', ['%nickname%' => 'Ghost']]], self::replies($this->run_('Ghost', '+i', targetOnline: false)));
        self::assertSame([['channel.protected_root', ['%nickname%' => 'Target']]], self::replies($this->run_('Target', '+i', operclass: 'netadmin', roots: ['Target'])));
        self::assertSame([['channel.protected_ircop', ['%nickname%' => 'Target']]], self::replies($this->run_('Target', '+i', operclass: 'netadmin', targetIsRoleIrcop: true)));
        self::assertSame([], $this->applied);
    }

    #[Test]
    public function staysSilentWhenTheNetworkIsUnavailableOrThereIsNoSender(): void
    {
        self::assertSame([], self::replies($this->run_('Target', '+i', networkUp: false)));
        self::assertSame([], $this->audit->records);

        $harness = new ChannelOverrideCommandHarness();
        new UmodeCommand($this->handler())->execute($harness->context('UMODE', ['Target', '+i'], $this->createStub(OperatorAuthorizationQuery::class), withSender: false));
        self::assertSame([], self::replies($harness));
    }

    /** @return list<array{string, array<string, mixed>}> the replies without the bot name every reply carries */
    private static function replies(ChannelOverrideCommandHarness $harness): array
    {
        return array_map(static fn (array $reply): array => [$reply[0], array_diff_key($reply[1], ['%bot%' => true])], $harness->replies);
    }

    /** @param list<string> $roots */
    private function run_(
        string $target,
        string $modes,
        ?string $operclass = null,
        array $roots = [],
        bool $targetOnline = true,
        bool $targetIsRoleIrcop = false,
        bool $networkUp = true,
        string $targetNick = 'Target',
    ): ChannelOverrideCommandHarness {
        $harness = new ChannelOverrideCommandHarness();
        new UmodeCommand($this->handler($operclass, $roots, $targetOnline, $targetIsRoleIrcop, $networkUp, $targetNick))
            ->execute($harness->context('UMODE', [$target, $modes], $this->createStub(OperatorAuthorizationQuery::class)));

        return $harness;
    }

    /** @param list<string> $roots */
    private function handler(
        ?string $operclass = null,
        array $roots = [],
        bool $targetOnline = true,
        bool $targetIsRoleIrcop = false,
        bool $networkUp = true,
        string $targetNick = 'Target',
    ): ChangeUserModesHandler {
        $users = $this->createStub(NetworkUserLookup::class);
        $users->method('findByNickname')->willReturn($targetOnline
            ? new NetworkUser('UID1', $targetNick, 'ident', 'host', 'ip', $targetIsRoleIrcop, $targetIsRoleIrcop)
            : null);
        $rootRegistry = $this->createStub(RootIdentityRegistry::class);
        $rootRegistry->method('contains')->willReturnCallback(static fn (string $nick): bool => in_array($nick, $roots, true));
        // Only the actor (Oper) has an account with a role; the target is a role IRCop on demand.
        $accounts = $this->createStub(OperatorAccountLookup::class);
        $accounts->method('findIdByNickname')->willReturnCallback(static fn (string $nick): ?int => match ($nick) {
            'oper' => null === $operclass ? null : 1,
            default => 2,
        });
        $roles = $this->createStub(OperatorRoleAccess::class);
        $roles->method('roleName')->willReturn('Staff');
        $roles->method('hasAssignedRole')->willReturn($targetIsRoleIrcop);
        $store = $this->createStub(OperatorRoleStore::class);
        $store->method('findByName')->willReturn(new OperatorRoleRecord(1, 'Staff', '', false, operclass: $operclass));
        $network = $this->createStub(UserModeNetworkActions::class);
        $network->method('apply')->willReturnCallback(function (string $uid, string $modes) use ($networkUp): bool {
            if ($networkUp) {
                $this->applied[] = [$uid, $modes];
            }

            return $networkUp;
        });

        return new ChangeUserModesHandler(
            $users,
            $rootRegistry,
            $accounts,
            $roles,
            $store,
            new ProtectedNetworkTargets($rootRegistry, $accounts, $roles),
            $network,
            $this->audit,
        );
    }
}
