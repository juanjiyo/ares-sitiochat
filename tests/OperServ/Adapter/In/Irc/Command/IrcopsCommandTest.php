<?php

declare(strict_types=1);

namespace App\Tests\OperServ\Adapter\In\Irc\Command;

use App\OperServ\Adapter\In\Irc\Command\IrcopsCommand;
use App\OperServ\Application\Port\In\OperatorAuthorizationQuery;
use App\OperServ\Application\Port\Out\ConnectedUsersDirectory;
use App\OperServ\Application\Port\Out\NetworkUser;
use App\OperServ\Application\UseCase\NetworkInfo\ListOnlineOperatorsHandler;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

use function array_column;

#[CoversClass(IrcopsCommand::class)]
#[CoversClass(ListOnlineOperatorsHandler::class)]
final class IrcopsCommandTest extends TestCase
{
    #[Test]
    public function exposesIrcopOnlyHelpMetadataWithoutPermission(): void
    {
        $command = new IrcopsCommand($this->handler([]));

        self::assertSame('IRCOPS', $command->getName());
        self::assertSame([], $command->getAliases());
        self::assertSame(0, $command->getMinArgs());
        self::assertSame('ircops.syntax', $command->getSyntaxKey());
        self::assertSame('ircops.help', $command->getHelpKey());
        self::assertSame('ircops.example', $command->getExampleKey());
        self::assertSame(31, $command->getOrder());
        self::assertSame('ircops.short', $command->getShortDescKey());
        self::assertSame([], $command->getSubCommandHelp());
        self::assertTrue($command->isOperOnly());
        self::assertNull($command->getRequiredPermission());
        self::assertSame([], $command->getHelpParams());
    }

    #[Test]
    public function listsOnlineOperatorsBetweenHeaderAndFooter(): void
    {
        $harness = new ChannelOverrideCommandHarness();

        new IrcopsCommand($this->handler([
            new NetworkUser('U1', 'Zed', 'ident', 'host', '*', true, true),
            new NetworkUser('U2', 'User', 'ident', 'host', '*', false, false),
            new NetworkUser('U3', 'Ana', 'ident', 'host', '*', true, true),
        ]))->execute($harness->context('IRCOPS', [], $this->createStub(OperatorAuthorizationQuery::class)));

        self::assertSame(['ircops.header', 'ircops.entry', 'ircops.entry', 'ircops.footer'], array_column($harness->replies, 0));
        self::assertSame('2', $harness->replies[0][1]['%count%']);
        self::assertSame('Ana', $harness->replies[1][1]['%nickname%']);
        self::assertSame('Zed', $harness->replies[2][1]['%nickname%']);
    }

    #[Test]
    public function reportsWhenNoOperatorIsOnline(): void
    {
        $harness = new ChannelOverrideCommandHarness();

        new IrcopsCommand($this->handler([new NetworkUser('U2', 'User', 'ident', 'host', '*', false, false)]))
            ->execute($harness->context('IRCOPS', [], $this->createStub(OperatorAuthorizationQuery::class)));

        self::assertSame(['ircops.empty'], array_column($harness->replies, 0));
    }

    /** @param list<NetworkUser> $users */
    private function handler(array $users): ListOnlineOperatorsHandler
    {
        $directory = $this->createStub(ConnectedUsersDirectory::class);
        $directory->method('listConnected')->willReturn($users);

        return new ListOnlineOperatorsHandler($directory);
    }
}
