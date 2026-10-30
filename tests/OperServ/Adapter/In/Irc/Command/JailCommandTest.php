<?php

declare(strict_types=1);

namespace App\Tests\OperServ\Adapter\In\Irc\Command;

use App\OperServ\Adapter\In\Irc\Command\JailCommand;
use App\OperServ\Adapter\In\Irc\Command\JailPresenter;
use App\OperServ\Adapter\In\Irc\Command\UnjailCommand;
use App\OperServ\Application\Port\In\OperatorAuthorizationQuery;
use App\OperServ\Application\Port\Out\NetworkUser;
use App\OperServ\Application\Port\Out\NetworkUserLookup;
use App\OperServ\Application\Port\Out\OperatorAccountLookup;
use App\OperServ\Application\Port\Out\OperatorRoleAccess;
use App\OperServ\Application\Port\Out\RootIdentityRegistry;
use App\OperServ\Application\Security\OperServPermission;
use App\OperServ\Application\Security\ProtectedNetworkTargets;
use App\OperServ\Application\UseCase\Jail\JailResult;
use App\OperServ\Application\UseCase\Jail\JailRoster;
use App\OperServ\Application\UseCase\Jail\JailUser;
use App\OperServ\Application\UseCase\Jail\JailUserHandler;
use App\Tests\OperServ\Application\UseCase\ChannelOverride\RecordingCommandAuditRecorder;
use App\Tests\OperServ\Application\UseCase\Jail\RecordingJailNetworkActions;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

use function array_diff_key;
use function array_map;

#[CoversClass(JailCommand::class)]
#[CoversClass(UnjailCommand::class)]
#[CoversClass(JailPresenter::class)]
#[CoversClass(JailUser::class)]
#[CoversClass(JailUserHandler::class)]
#[CoversClass(JailResult::class)]
final class JailCommandTest extends TestCase
{
    private JailRoster $roster;

    private RecordingJailNetworkActions $network;

    private RecordingCommandAuditRecorder $audit;

    protected function setUp(): void
    {
        $this->roster = new JailRoster();
        $this->network = new RecordingJailNetworkActions(['UID1' => ['#SitioChat', '#sumidero', '#Zamora']]);
        $this->audit = new RecordingCommandAuditRecorder();
    }

    #[Test]
    public function presenterCannotBeInstantiated(): void
    {
        $reflection = new ReflectionClass(JailPresenter::class);
        $constructor = $reflection->getConstructor();

        self::assertNotNull($constructor);
        self::assertTrue($constructor->isPrivate());
        $constructor->invoke($reflection->newInstanceWithoutConstructor());
    }

    #[Test]
    public function exposesOperOnlyPermissionAndHelpMetadata(): void
    {
        $jail = new JailCommand($this->handler());
        $unjail = new UnjailCommand($this->handler());

        self::assertSame(['JAIL', 'UNJAIL'], [$jail->getName(), $unjail->getName()]);
        self::assertSame([[], []], [$jail->getAliases(), $unjail->getAliases()]);
        self::assertSame([1, 1], [$jail->getMinArgs(), $unjail->getMinArgs()]);
        self::assertSame(['jail.syntax', 'unjail.syntax'], [$jail->getSyntaxKey(), $unjail->getSyntaxKey()]);
        self::assertSame(['jail.help', 'unjail.help'], [$jail->getHelpKey(), $unjail->getHelpKey()]);
        self::assertSame(['jail.example', 'unjail.example'], [$jail->getExampleKey(), $unjail->getExampleKey()]);
        self::assertSame([26, 27], [$jail->getOrder(), $unjail->getOrder()]);
        self::assertSame(['jail.short', 'unjail.short'], [$jail->getShortDescKey(), $unjail->getShortDescKey()]);
        self::assertSame([[], []], [$jail->getSubCommandHelp(), $unjail->getSubCommandHelp()]);
        self::assertTrue($jail->isOperOnly() && $unjail->isOperOnly());
        self::assertSame([OperServPermission::JAIL, OperServPermission::JAIL], [$jail->getRequiredPermission(), $unjail->getRequiredPermission()]);
        self::assertSame(['%channel%' => '#Sumidero'], $jail->getHelpParams());
        self::assertSame([], $unjail->getHelpParams());
    }

    #[Test]
    public function jailsAUserIntoTheJailChannelOnlyAndReleasesThem(): void
    {
        $jailed = $this->run_(new JailCommand($this->handler()), 'Target');

        self::assertSame([
            ['join', 'UID1', '#Sumidero'],
            ['part', 'UID1', '#SitioChat'],
            ['part', 'UID1', '#Zamora'],
        ], $this->network->calls);
        self::assertTrue($this->roster->contains('UID1'));
        self::assertSame([['jail.done', ['%nickname%' => 'Target', '%channel%' => '#Sumidero']]], $jailed);
        self::assertSame('JAIL', $this->audit->records[0]->operation);
        self::assertSame(OperServPermission::JAIL, $this->audit->records[0]->permission);
        self::assertSame(['channel' => '#Sumidero'], $this->audit->records[0]->metadata);

        self::assertSame([['jail.already', ['%nickname%' => 'Target', '%channel%' => '#Sumidero']]], $this->run_(new JailCommand($this->handler()), 'Target'));

        $released = $this->run_(new UnjailCommand($this->handler()), 'Target');
        self::assertSame([['unjail.done', ['%nickname%' => 'Target', '%channel%' => '#Sumidero']]], $released);
        self::assertFalse($this->roster->contains('UID1'));
        self::assertSame('UNJAIL', $this->audit->records[1]->operation);

        self::assertSame([['unjail.not_jailed', ['%nickname%' => 'Target', '%channel%' => '#Sumidero']]], $this->run_(new UnjailCommand($this->handler()), 'Target'));
    }

    #[Test]
    public function refusesOfflineAndProtectedUsers(): void
    {
        $params = ['%nickname%' => 'Target', '%channel%' => '#Sumidero'];

        self::assertSame([['channel.user_not_online', $params]], $this->run_(new JailCommand($this->handler(online: false)), 'Target'));
        self::assertSame([['channel.user_not_online', $params]], $this->run_(new UnjailCommand($this->handler(online: false)), 'Target'));
        self::assertSame([['channel.protected_root', $params]], $this->run_(new JailCommand($this->handler(root: true)), 'Target'));
        self::assertSame([['channel.protected_ircop', $params]], $this->run_(new JailCommand($this->handler(roleIrcop: true)), 'Target'));
        self::assertSame([], $this->network->calls);
        self::assertSame([], $this->audit->records);
    }

    #[Test]
    public function staysSilentWithoutNetworkOrSender(): void
    {
        $this->network->up = false;
        self::assertSame([], $this->run_(new JailCommand($this->handler()), 'Target'));
        self::assertFalse($this->roster->contains('UID1'));

        $harness = new ChannelOverrideCommandHarness();
        new JailCommand($this->handler())->execute($harness->context('JAIL', ['Target'], $this->createStub(OperatorAuthorizationQuery::class), withSender: false));
        new UnjailCommand($this->handler())->execute($harness->context('UNJAIL', ['Target'], $this->createStub(OperatorAuthorizationQuery::class), withSender: false));
        self::assertSame([], $harness->replies);
    }

    /** @return list<array{string, array<string, mixed>}> the replies without the bot name every reply carries */
    private function run_(JailCommand|UnjailCommand $command, string $target): array
    {
        $harness = new ChannelOverrideCommandHarness();
        $command->execute($harness->context($command->getName(), [$target], $this->createStub(OperatorAuthorizationQuery::class)));

        return array_map(static fn (array $reply): array => [$reply[0], array_diff_key($reply[1], ['%bot%' => true])], $harness->replies);
    }

    private function handler(bool $online = true, bool $root = false, bool $roleIrcop = false): JailUserHandler
    {
        $users = $this->createStub(NetworkUserLookup::class);
        $users->method('findByNickname')->willReturn($online ? new NetworkUser('UID1', 'Target', 'ident', 'host', 'ip', $roleIrcop, $roleIrcop) : null);
        $roots = $this->createStub(RootIdentityRegistry::class);
        $roots->method('contains')->willReturn($root);
        $accounts = $this->createStub(OperatorAccountLookup::class);
        $accounts->method('findIdByNickname')->willReturn(7);
        $roles = $this->createStub(OperatorRoleAccess::class);
        $roles->method('hasAssignedRole')->willReturn($roleIrcop);

        return new JailUserHandler($users, new ProtectedNetworkTargets($roots, $accounts, $roles), $this->network, $this->roster, $this->audit, '#Sumidero');
    }
}
