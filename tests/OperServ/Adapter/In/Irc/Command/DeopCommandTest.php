<?php

declare(strict_types=1);

namespace App\Tests\OperServ\Adapter\In\Irc\Command;

use App\OperServ\Adapter\In\Irc\Command\ChannelOverridePresenter;
use App\OperServ\Adapter\In\Irc\Command\DeopCommand;
use App\OperServ\Application\Port\In\OperatorAuthorizationQuery;
use App\OperServ\Application\Port\Out\NetworkChannel;
use App\OperServ\Application\Port\Out\NetworkChannelLookup;
use App\OperServ\Application\Port\Out\NetworkUser;
use App\OperServ\Application\Port\Out\NetworkUserLookup;
use App\OperServ\Application\Port\Out\OperatorAccountLookup;
use App\OperServ\Application\Port\Out\OperatorRoleAccess;
use App\OperServ\Application\Port\Out\RootIdentityRegistry;
use App\OperServ\Application\Security\OperServPermission;
use App\OperServ\Application\Security\ProtectedNetworkTargets;
use App\OperServ\Application\UseCase\ChannelOverride\SetChannelOperator;
use App\OperServ\Application\UseCase\ChannelOverride\SetChannelOperatorHandler;
use App\Tests\OperServ\Application\UseCase\ChannelOverride\RecordingChannelOverrideActions;
use App\Tests\OperServ\Application\UseCase\ChannelOverride\RecordingCommandAuditRecorder;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(DeopCommand::class)]
#[CoversClass(ChannelOverridePresenter::class)]
#[CoversClass(SetChannelOperator::class)]
#[CoversClass(SetChannelOperatorHandler::class)]
final class DeopCommandTest extends TestCase
{
    #[Test]
    public function exposesOperOnlyPermissionAndHelpMetadata(): void
    {
        $command = new DeopCommand($this->handler(new RecordingChannelOverrideActions()));

        self::assertSame('DEOP', $command->getName());
        self::assertSame([], $command->getAliases());
        self::assertSame(2, $command->getMinArgs());
        self::assertSame('deop.syntax', $command->getSyntaxKey());
        self::assertSame('deop.help', $command->getHelpKey());
        self::assertSame('deop.example', $command->getExampleKey());
        self::assertSame(13, $command->getOrder());
        self::assertSame('deop.short', $command->getShortDescKey());
        self::assertSame([], $command->getSubCommandHelp());
        self::assertTrue($command->isOperOnly());
        self::assertSame(OperServPermission::OP, $command->getRequiredPermission());
        self::assertSame([], $command->getHelpParams());
    }

    #[Test]
    public function removesOperatorStatusAndPresentsSuccess(): void
    {
        $network = new RecordingChannelOverrideActions();
        $audit = new RecordingCommandAuditRecorder();
        $harness = new ChannelOverrideCommandHarness();

        new DeopCommand($this->handler($network, audit: $audit))
            ->execute($harness->context('DEOP', ['#help', 'Target'], $this->createStub(OperatorAuthorizationQuery::class)));

        self::assertSame([['operator', '#help', ['UID1', '-o']]], $network->calls);
        self::assertSame('DEOP', $audit->records[0]->operation);
        self::assertCount(1, $harness->replies);
        self::assertSame('deop.done', $harness->replies[0][0]);
        self::assertSame('Target', $harness->replies[0][1]['%nickname%']);
        self::assertSame('#help', $harness->replies[0][1]['%channel%']);
    }

    /** @return iterable<string, array{bool, bool, string}> */
    public static function protectedTargets(): iterable
    {
        yield 'root' => [true, false, 'channel.protected_root'];
        yield 'ircop with role' => [false, true, 'channel.protected_ircop'];
    }

    #[Test]
    #[DataProvider('protectedTargets')]
    public function presentsProtectedTargetsWithoutNetworkEffect(bool $root, bool $operatorWithRole, string $key): void
    {
        $network = new RecordingChannelOverrideActions();
        $harness = new ChannelOverrideCommandHarness();

        new DeopCommand($this->handler($network, $root, $operatorWithRole))
            ->execute($harness->context('DEOP', ['#help', 'Target'], $this->createStub(OperatorAuthorizationQuery::class)));

        self::assertSame([], $network->calls);
        self::assertSame($key, $harness->replies[0][0]);
        self::assertSame('Target', $harness->replies[0][1]['%nickname%']);
    }

    #[Test]
    public function missingSenderDoesNotReachTheUseCase(): void
    {
        $network = new RecordingChannelOverrideActions();
        $harness = new ChannelOverrideCommandHarness();

        new DeopCommand($this->handler($network))
            ->execute($harness->context('DEOP', ['#help', 'Target'], $this->createStub(OperatorAuthorizationQuery::class), false));

        self::assertSame([], $network->calls);
        self::assertSame([], $harness->replies);
    }

    private function handler(
        RecordingChannelOverrideActions $network,
        bool $root = false,
        bool $operatorWithRole = false,
        ?RecordingCommandAuditRecorder $audit = null,
    ): SetChannelOperatorHandler {
        $channels = $this->createStub(NetworkChannelLookup::class);
        $channels->method('findByName')->willReturn(new NetworkChannel('#help', ['UID1']));
        $users = $this->createStub(NetworkUserLookup::class);
        $users->method('findByNickname')->willReturn(new NetworkUser('UID1', 'Target', 'ident', 'host', 'ip', $operatorWithRole, $operatorWithRole));
        $roots = $this->createStub(RootIdentityRegistry::class);
        $roots->method('contains')->willReturn($root);
        $accounts = $this->createStub(OperatorAccountLookup::class);
        $accounts->method('findIdByNickname')->willReturn(7);
        $roles = $this->createStub(OperatorRoleAccess::class);
        $roles->method('hasAssignedRole')->willReturn($operatorWithRole);

        return new SetChannelOperatorHandler($channels, $users, new ProtectedNetworkTargets($roots, $accounts, $roles), $network, $audit ?? new RecordingCommandAuditRecorder());
    }
}
