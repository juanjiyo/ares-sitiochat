<?php

declare(strict_types=1);

namespace App\Tests\OperServ\Adapter\In\Irc\Command;

use App\OperServ\Adapter\In\Irc\Command\ChannelOverridePresenter;
use App\OperServ\Adapter\In\Irc\Command\KickCommand;
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
use App\OperServ\Application\UseCase\ChannelOverride\KickChannelMember;
use App\OperServ\Application\UseCase\ChannelOverride\KickChannelMemberHandler;
use App\Tests\OperServ\Application\UseCase\ChannelOverride\RecordingChannelOverrideActions;
use App\Tests\OperServ\Application\UseCase\ChannelOverride\RecordingCommandAuditRecorder;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(KickCommand::class)]
#[CoversClass(ChannelOverridePresenter::class)]
#[CoversClass(KickChannelMember::class)]
#[CoversClass(KickChannelMemberHandler::class)]
final class KickCommandTest extends TestCase
{
    #[Test]
    public function exposesOperOnlyPermissionAndHelpMetadata(): void
    {
        $command = new KickCommand($this->handler(new RecordingChannelOverrideActions()));

        self::assertSame('KICK', $command->getName());
        self::assertSame([], $command->getAliases());
        self::assertSame(2, $command->getMinArgs());
        self::assertSame('kick.syntax', $command->getSyntaxKey());
        self::assertSame('kick.help', $command->getHelpKey());
        self::assertSame('kick.example', $command->getExampleKey());
        self::assertSame(11, $command->getOrder());
        self::assertSame('kick.short', $command->getShortDescKey());
        self::assertSame([], $command->getSubCommandHelp());
        self::assertTrue($command->isOperOnly());
        self::assertSame(OperServPermission::KICK, $command->getRequiredPermission());
        self::assertSame([], $command->getHelpParams());
    }

    #[Test]
    public function kicksWithJoinedReasonAndPresentsSuccess(): void
    {
        $network = new RecordingChannelOverrideActions();
        $audit = new RecordingCommandAuditRecorder();
        $harness = new ChannelOverrideCommandHarness();

        new KickCommand($this->handler($network, audit: $audit))
            ->execute($harness->context('KICK', ['#help', 'Target', 'stop', 'flooding'], $this->createStub(OperatorAuthorizationQuery::class)));

        self::assertSame([['kick', '#help', ['UID1', 'stop flooding']]], $network->calls);
        self::assertSame('Oper', $audit->records[0]->actor);
        self::assertSame('stop flooding', $audit->records[0]->reason);
        self::assertCount(1, $harness->replies);
        self::assertSame('kick.done', $harness->replies[0][0]);
        self::assertSame('Target', $harness->replies[0][1]['%nickname%']);
        self::assertSame('#help', $harness->replies[0][1]['%channel%']);
        self::assertSame('stop flooding', $harness->replies[0][1]['%reason%']);
        self::assertCount(1, $harness->notices);
    }

    #[Test]
    public function kicksWithServiceReasonWhenReasonIsOmitted(): void
    {
        $network = new RecordingChannelOverrideActions();
        $harness = new ChannelOverrideCommandHarness();

        new KickCommand($this->handler($network))
            ->execute($harness->context('KICK', ['#help', 'Target'], $this->createStub(OperatorAuthorizationQuery::class)));

        self::assertSame([['kick', '#help', ['UID1', 'Kicked by OperServ']]], $network->calls);
        self::assertSame('Kicked by OperServ', $harness->replies[0][1]['%reason%']);
    }

    /** @return iterable<string, array{list<string>, string, bool, bool, string, array<string, string>}> */
    public static function refusals(): iterable
    {
        yield 'invalid channel' => [['help', 'Target'], 'online', true, false, 'channel.invalid_name', ['%channel%' => 'help']];
        yield 'missing channel' => [['#help', 'Target'], 'online', false, false, 'channel.not_found', ['%channel%' => '#help']];
        yield 'offline target' => [['#help', 'Ghost'], 'offline', true, false, 'channel.user_not_online', ['%nickname%' => 'Ghost']];
        yield 'not a member' => [['#help', 'Target'], 'outside', true, false, 'channel.user_not_on_channel', ['%nickname%' => 'Target', '%channel%' => '#help']];
        yield 'root target' => [['#help', 'Target'], 'online', true, true, 'channel.protected_root', ['%nickname%' => 'Target']];
        yield 'ircop target' => [['#help', 'Target'], 'ircop', true, false, 'channel.protected_ircop', ['%nickname%' => 'Target']];
    }

    /**
     * @param list<string>          $arguments
     * @param array<string, string> $parameters
     */
    #[Test]
    #[DataProvider('refusals')]
    public function presentsRefusalsWithoutNetworkEffect(array $arguments, string $target, bool $channelExists, bool $root, string $key, array $parameters): void
    {
        $network = new RecordingChannelOverrideActions();
        $harness = new ChannelOverrideCommandHarness();

        new KickCommand($this->handler($network, $target, $channelExists, $root))
            ->execute($harness->context('KICK', $arguments, $this->createStub(OperatorAuthorizationQuery::class)));

        self::assertSame([], $network->calls);
        self::assertCount(1, $harness->replies);
        self::assertSame($key, $harness->replies[0][0]);
        foreach ($parameters as $name => $value) {
            self::assertSame($value, $harness->replies[0][1][$name]);
        }
    }

    #[Test]
    public function networkFailureIsSilent(): void
    {
        $harness = new ChannelOverrideCommandHarness();

        new KickCommand($this->handler(new RecordingChannelOverrideActions(false)))
            ->execute($harness->context('KICK', ['#help', 'Target'], $this->createStub(OperatorAuthorizationQuery::class)));

        self::assertSame([], $harness->replies);
        self::assertSame([], $harness->notices);
    }

    #[Test]
    public function missingSenderDoesNotReachTheUseCase(): void
    {
        $channels = $this->createMock(NetworkChannelLookup::class);
        $channels->expects(self::never())->method('findByName');
        $harness = new ChannelOverrideCommandHarness();

        new KickCommand(new KickChannelMemberHandler(
            $channels,
            $this->createStub(NetworkUserLookup::class),
            $this->protection(false, false),
            new RecordingChannelOverrideActions(),
            new RecordingCommandAuditRecorder(),
        ))->execute($harness->context('KICK', ['#help', 'Target'], $this->createStub(OperatorAuthorizationQuery::class), false));

        self::assertSame([], $harness->replies);
    }

    private function handler(
        RecordingChannelOverrideActions $network,
        string $target = 'online',
        bool $channelExists = true,
        bool $root = false,
        ?RecordingCommandAuditRecorder $audit = null,
    ): KickChannelMemberHandler {
        $channels = $this->createStub(NetworkChannelLookup::class);
        $channels->method('findByName')->willReturn($channelExists ? new NetworkChannel('#help', 'outside' === $target ? [] : ['UID1']) : null);
        $users = $this->createStub(NetworkUserLookup::class);
        $users->method('findByNickname')->willReturn('offline' === $target ? null : new NetworkUser('UID1', 'Target', 'ident', 'host', 'ip', 'ircop' === $target, 'ircop' === $target));

        return new KickChannelMemberHandler($channels, $users, $this->protection($root, 'ircop' === $target), $network, $audit ?? new RecordingCommandAuditRecorder());
    }

    private function protection(bool $root, bool $operatorWithRole): ProtectedNetworkTargets
    {
        $roots = $this->createStub(RootIdentityRegistry::class);
        $roots->method('contains')->willReturn($root);
        $accounts = $this->createStub(OperatorAccountLookup::class);
        $accounts->method('findIdByNickname')->willReturn(7);
        $roles = $this->createStub(OperatorRoleAccess::class);
        $roles->method('hasAssignedRole')->willReturn($operatorWithRole);

        return new ProtectedNetworkTargets($roots, $accounts, $roles);
    }
}
