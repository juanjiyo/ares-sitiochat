<?php

declare(strict_types=1);

namespace App\Tests\OperServ\Adapter\In\Irc\Command;

use App\OperServ\Adapter\In\Irc\Command\BlockCommand;
use App\OperServ\Adapter\In\Irc\Command\CloneHostBanPresenter;
use App\OperServ\Application\Port\In\OperatorAuthorizationQuery;
use App\OperServ\Application\Security\OperServPermission;
use App\OperServ\Application\Security\ProtectedNetworkTargets;
use App\OperServ\Application\UseCase\Clones\BanCloneHost;
use App\OperServ\Application\UseCase\Clones\BanCloneHostHandler;
use App\OperServ\Application\UseCase\Clones\BanCloneHostResult;
use App\OperServ\Application\UseCase\Clones\CloneHostBan;
use App\OperServ\Application\UseCase\Clones\ConnectionHosts;
use App\OperServ\Application\UseCase\ManageGline\ManageGline;
use App\OperServ\Application\UseCase\ManageGline\ManageGlineHandlerInterface;
use App\OperServ\Application\UseCase\ManageGline\ManageGlineOutcome;
use App\OperServ\Application\UseCase\ManageGline\ManageGlineResult;
use App\Tests\OperServ\Application\UseCase\ChannelOverride\RecordingCommandAuditRecorder;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

use function array_column;

#[CoversClass(BlockCommand::class)]
#[CoversClass(CloneHostBanPresenter::class)]
#[CoversClass(BanCloneHost::class)]
#[CoversClass(BanCloneHostHandler::class)]
#[CoversClass(BanCloneHostResult::class)]
#[CoversClass(CloneHostBan::class)]
#[CoversClass(ConnectionHosts::class)]
#[CoversClass(ProtectedNetworkTargets::class)]
final class BlockCommandTest extends TestCase
{
    use CloneCommandFixtures;

    #[Test]
    public function exposesOperOnlyPermissionAndHelpMetadata(): void
    {
        $command = new BlockCommand($this->banHandler($this->createStub(ManageGlineHandlerInterface::class)));

        self::assertSame('BLOCK', $command->getName());
        self::assertSame([], $command->getAliases());
        self::assertSame(1, $command->getMinArgs());
        self::assertSame('block.syntax', $command->getSyntaxKey());
        self::assertSame('block.help', $command->getHelpKey());
        self::assertSame('block.example', $command->getExampleKey());
        self::assertSame(22, $command->getOrder());
        self::assertSame('block.short', $command->getShortDescKey());
        self::assertSame([], $command->getSubCommandHelp());
        self::assertTrue($command->isOperOnly());
        self::assertSame(OperServPermission::CLONES, $command->getRequiredPermission());
        self::assertSame([], $command->getHelpParams());
    }

    #[Test]
    public function glinesTheTargetHostForFiveHoursWithTheDefaultReason(): void
    {
        $audit = new RecordingCommandAuditRecorder();
        $glines = $this->createMock(ManageGlineHandlerInterface::class);
        $glines->expects(self::once())->method('handle')
            ->with(self::callback(static fn (ManageGline $command): bool => '*@198.51.100.7' === $command->mask
                && '5h' === $command->expiry
                && 'Blocked by OperServ' === $command->reason))
            ->willReturn(new ManageGlineResult(ManageGlineOutcome::Added, '*@198.51.100.7', expiry: '5h', reason: 'Blocked by OperServ'));
        $harness = new ChannelOverrideCommandHarness();

        new BlockCommand($this->banHandler($glines, $audit))
            ->execute($harness->context('BLOCK', ['Clone'], $this->createStub(OperatorAuthorizationQuery::class)));

        self::assertSame(['block.done'], array_column($harness->replies, 0));
        self::assertSame('Clone', $harness->replies[0][1]['%nickname%']);
        self::assertSame('5h', $harness->replies[0][1]['%duration%']);
        self::assertSame('Blocked by OperServ', $harness->replies[0][1]['%reason%']);
        self::assertSame('BLOCK', $audit->records[0]->operation);
    }

    #[Test]
    public function missingSenderDoesNotReachTheUseCase(): void
    {
        $glines = $this->createMock(ManageGlineHandlerInterface::class);
        $glines->expects(self::never())->method('handle');
        $harness = new ChannelOverrideCommandHarness();

        new BlockCommand($this->banHandler($glines))
            ->execute($harness->context('BLOCK', ['Target'], $this->createStub(OperatorAuthorizationQuery::class), false));

        self::assertSame([], $harness->replies);
    }
}
