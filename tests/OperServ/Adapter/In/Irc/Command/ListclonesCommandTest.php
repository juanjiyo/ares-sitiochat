<?php

declare(strict_types=1);

namespace App\Tests\OperServ\Adapter\In\Irc\Command;

use App\OperServ\Adapter\In\Irc\Command\ListclonesCommand;
use App\OperServ\Application\Port\In\OperatorAuthorizationQuery;
use App\OperServ\Application\Security\OperServPermission;
use App\OperServ\Application\UseCase\Clones\CloneSession;
use App\OperServ\Application\UseCase\Clones\ConnectionHosts;
use App\OperServ\Application\UseCase\Clones\ListClones;
use App\OperServ\Application\UseCase\Clones\ListClonesHandler;
use App\OperServ\Application\UseCase\Clones\ListClonesResult;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

use function array_column;

#[CoversClass(ListclonesCommand::class)]
#[CoversClass(ListClones::class)]
#[CoversClass(ListClonesHandler::class)]
#[CoversClass(ListClonesResult::class)]
#[CoversClass(CloneSession::class)]
#[CoversClass(ConnectionHosts::class)]
final class ListclonesCommandTest extends TestCase
{
    use CloneCommandFixtures;

    #[Test]
    public function exposesOperOnlyPermissionAndHelpMetadata(): void
    {
        $command = $this->command();

        self::assertSame('LISTCLONES', $command->getName());
        self::assertSame([], $command->getAliases());
        self::assertSame(1, $command->getMinArgs());
        self::assertSame('listclones.syntax', $command->getSyntaxKey());
        self::assertSame('listclones.help', $command->getHelpKey());
        self::assertSame('listclones.example', $command->getExampleKey());
        self::assertSame(32, $command->getOrder());
        self::assertSame('listclones.short', $command->getShortDescKey());
        self::assertSame([], $command->getSubCommandHelp());
        self::assertTrue($command->isOperOnly());
        self::assertSame(OperServPermission::CLONES, $command->getRequiredPermission());
        self::assertSame([], $command->getHelpParams());
    }

    #[Test]
    public function listsTheClonesOfTheTargetHost(): void
    {
        $harness = new ChannelOverrideCommandHarness();

        $this->command()->execute($harness->context('LISTCLONES', ['target'], $this->createStub(OperatorAuthorizationQuery::class)));

        self::assertSame(['listclones.header', 'listclones.entry', 'listclones.footer'], array_column($harness->replies, 0));
        self::assertSame('Target', $harness->replies[0][1]['%nickname%']);
        self::assertSame('198.51.100.7', $harness->replies[0][1]['%host%']);
        self::assertSame('2', $harness->replies[0][1]['%count%']);
        self::assertSame('Clone', $harness->replies[1][1]['%nickname%']);
        self::assertSame('cid', $harness->replies[1][1]['%ident%']);
    }

    #[Test]
    public function reportsATargetWithoutClones(): void
    {
        $harness = new ChannelOverrideCommandHarness();

        $this->command()->execute($harness->context('LISTCLONES', ['Other'], $this->createStub(OperatorAuthorizationQuery::class)));

        self::assertSame(['listclones.none'], array_column($harness->replies, 0));
        self::assertSame('Other', $harness->replies[0][1]['%nickname%']);
        self::assertSame('203.0.113.9', $harness->replies[0][1]['%host%']);
    }

    #[Test]
    public function reportsATargetThatIsNotOnline(): void
    {
        $harness = new ChannelOverrideCommandHarness();

        $this->command()->execute($harness->context('LISTCLONES', ['Ghost'], $this->createStub(OperatorAuthorizationQuery::class)));

        self::assertSame(['channel.user_not_online'], array_column($harness->replies, 0));
        self::assertSame('Ghost', $harness->replies[0][1]['%nickname%']);
    }

    private function command(): ListclonesCommand
    {
        return new ListclonesCommand(new ListClonesHandler($this->cloneUsers(), $this->cloneHosts()));
    }
}
