<?php

declare(strict_types=1);

namespace App\Tests\OperServ\Adapter\In\Irc\Command;

use App\OperServ\Adapter\In\Irc\Command\CloneHostBanPresenter;
use App\OperServ\Adapter\In\Irc\Command\KillclonesCommand;
use App\OperServ\Application\Port\In\OperatorAuthorizationQuery;
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
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

use function array_column;

#[CoversClass(KillclonesCommand::class)]
#[CoversClass(CloneHostBanPresenter::class)]
#[CoversClass(BanCloneHost::class)]
#[CoversClass(BanCloneHostHandler::class)]
#[CoversClass(BanCloneHostResult::class)]
#[CoversClass(CloneHostBan::class)]
#[CoversClass(ConnectionHosts::class)]
#[CoversClass(ProtectedNetworkTargets::class)]
final class KillclonesCommandTest extends TestCase
{
    use CloneCommandFixtures;

    #[Test]
    public function exposesOperOnlyPermissionAndHelpMetadata(): void
    {
        $command = new KillclonesCommand($this->banHandler($this->createStub(ManageGlineHandlerInterface::class)));

        self::assertSame('KILLCLONES', $command->getName());
        self::assertSame([], $command->getAliases());
        self::assertSame(1, $command->getMinArgs());
        self::assertSame('killclones.syntax', $command->getSyntaxKey());
        self::assertSame('killclones.help', $command->getHelpKey());
        self::assertSame('killclones.example', $command->getExampleKey());
        self::assertSame(21, $command->getOrder());
        self::assertSame('killclones.short', $command->getShortDescKey());
        self::assertSame([], $command->getSubCommandHelp());
        self::assertTrue($command->isOperOnly());
        self::assertSame(OperServPermission::CLONES, $command->getRequiredPermission());
        self::assertSame([], $command->getHelpParams());
    }

    #[Test]
    public function glinesTheTargetHostForOneHourAndPresentsSuccess(): void
    {
        $audit = new RecordingCommandAuditRecorder();
        $glines = $this->createMock(ManageGlineHandlerInterface::class);
        $glines->expects(self::once())->method('handle')
            ->with(self::callback(static fn (ManageGline $command): bool => GlineAction::Add === $command->action
                && 'Oper' === $command->actor
                && '*@198.51.100.7' === $command->mask
                && '1h' === $command->expiry
                && 'Stop cloning now' === $command->reason))
            ->willReturn(new ManageGlineResult(ManageGlineOutcome::Added, '*@198.51.100.7', expiry: '1h', reason: 'Stop cloning now'));
        $harness = new ChannelOverrideCommandHarness();

        new KillclonesCommand($this->banHandler($glines, $audit))
            ->execute($harness->context('KILLCLONES', ['target', 'Stop', 'cloning', 'now'], $this->createStub(OperatorAuthorizationQuery::class)));

        self::assertSame(['killclones.done'], array_column($harness->replies, 0));
        $params = $harness->replies[0][1];
        self::assertSame('Target', $params['%nickname%']);
        self::assertSame('198.51.100.7', $params['%host%']);
        self::assertSame('*@198.51.100.7', $params['%mask%']);
        self::assertSame('1h', $params['%duration%']);
        self::assertSame('Stop cloning now', $params['%reason%']);
        self::assertSame('2', $params['%count%']);
        self::assertSame('KILLCLONES', $audit->records[0]->operation);
    }

    /** @return iterable<string, array{list<string>, ?ManageGlineResult, string, array<string, string>}> */
    public static function refusals(): iterable
    {
        yield 'not online' => [['Ghost'], null, 'channel.user_not_online', ['%nickname%' => 'Ghost']];
        yield 'loopback' => [['Local'], null, 'clones.loopback_host', ['%host%' => '127.0.0.1']];
        yield 'root' => [['Root'], null, 'channel.protected_root', ['%nickname%' => 'Root']];
        yield 'already glined' => [['Target'], new ManageGlineResult(ManageGlineOutcome::AlreadyExists, '*@198.51.100.7'), 'gline.already_exists', ['%mask%' => '*@198.51.100.7']];
        yield 'unsafe host' => [['Target'], new ManageGlineResult(ManageGlineOutcome::DangerousMask, '*@198.51.100.7'), 'gline.dangerous_mask', ['%mask%' => '*@198.51.100.7']];
        yield 'protected gline subject' => [['Target'], new ManageGlineResult(ManageGlineOutcome::ProtectedUser, protectedNickname: 'Admin'), 'gline.protected_user', ['%nickname%' => 'Admin']];
        yield 'limit reached' => [['Target'], new ManageGlineResult(ManageGlineOutcome::LimitReached, limit: 1000), 'gline.max_entries', ['%max%' => '1000']];
        yield 'failed' => [['Target'], new ManageGlineResult(ManageGlineOutcome::InvalidExpiry), 'clones.failed', ['%mask%' => '*@198.51.100.7']];
    }

    /**
     * @param list<string>          $arguments
     * @param array<string, string> $expectedParams
     */
    #[Test]
    #[DataProvider('refusals')]
    public function presentsEveryRefusal(array $arguments, ?ManageGlineResult $glineResult, string $expectedKey, array $expectedParams): void
    {
        $glines = $this->createStub(ManageGlineHandlerInterface::class);
        $glines->method('handle')->willReturn($glineResult ?? new ManageGlineResult(ManageGlineOutcome::InvalidRequest));
        $harness = new ChannelOverrideCommandHarness();

        new KillclonesCommand($this->banHandler($glines))
            ->execute($harness->context('KILLCLONES', $arguments, $this->createStub(OperatorAuthorizationQuery::class)));

        self::assertSame([$expectedKey], array_column($harness->replies, 0));
        foreach ($expectedParams as $name => $value) {
            self::assertSame($value, $harness->replies[0][1][$name]);
        }
    }

    #[Test]
    public function presentsAProtectedIrcOperatorSharingTheHost(): void
    {
        $harness = new ChannelOverrideCommandHarness();
        $result = BanCloneHostResult::refused(BanCloneHostOutcome::ProtectedIrcOperator, 'Target', '198.51.100.7', protectedNickname: 'Oper2');

        CloneHostBanPresenter::present($harness->context('KILLCLONES', ['Target'], $this->createStub(OperatorAuthorizationQuery::class)), $result, 'killclones.done');

        self::assertSame(['channel.protected_ircop'], array_column($harness->replies, 0));
        self::assertSame('Oper2', $harness->replies[0][1]['%nickname%']);
    }

    #[Test]
    public function missingSenderDoesNotReachTheUseCase(): void
    {
        $glines = $this->createMock(ManageGlineHandlerInterface::class);
        $glines->expects(self::never())->method('handle');
        $harness = new ChannelOverrideCommandHarness();

        new KillclonesCommand($this->banHandler($glines))
            ->execute($harness->context('KILLCLONES', ['Target'], $this->createStub(OperatorAuthorizationQuery::class), false));

        self::assertSame([], $harness->replies);
    }
}
