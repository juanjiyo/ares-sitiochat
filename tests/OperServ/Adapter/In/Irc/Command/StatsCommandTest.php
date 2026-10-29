<?php

declare(strict_types=1);

namespace App\Tests\OperServ\Adapter\In\Irc\Command;

use App\OperServ\Adapter\In\Irc\Command\StatsCommand;
use App\OperServ\Application\Port\In\OperatorAuthorizationQuery;
use App\OperServ\Application\Port\Out\ConnectedUsersDirectory;
use App\OperServ\Application\Port\Out\NetworkUser;
use App\OperServ\Application\UseCase\NetworkInfo\CountOnlineUsersHandler;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(StatsCommand::class)]
#[CoversClass(CountOnlineUsersHandler::class)]
final class StatsCommandTest extends TestCase
{
    #[Test]
    public function exposesIrcopOnlyHelpMetadataWithoutPermission(): void
    {
        $command = new StatsCommand($this->handler(0));

        self::assertSame('STATS', $command->getName());
        self::assertSame([], $command->getAliases());
        self::assertSame(0, $command->getMinArgs());
        self::assertSame('stats.syntax', $command->getSyntaxKey());
        self::assertSame('stats.help', $command->getHelpKey());
        self::assertSame('stats.example', $command->getExampleKey());
        self::assertSame(30, $command->getOrder());
        self::assertSame('stats.short', $command->getShortDescKey());
        self::assertSame([], $command->getSubCommandHelp());
        self::assertTrue($command->isOperOnly());
        self::assertNull($command->getRequiredPermission());
        self::assertSame([], $command->getHelpParams());
    }

    #[Test]
    public function presentsTheNumberOfUsersOnline(): void
    {
        $harness = new ChannelOverrideCommandHarness();

        new StatsCommand($this->handler(3))
            ->execute($harness->context('STATS', [], $this->createStub(OperatorAuthorizationQuery::class)));

        self::assertCount(1, $harness->replies);
        self::assertSame('stats.users', $harness->replies[0][0]);
        self::assertSame('3', $harness->replies[0][1]['%count%']);
    }

    private function handler(int $users): CountOnlineUsersHandler
    {
        $connected = [];
        for ($index = 0; $index < $users; ++$index) {
            $connected[] = new NetworkUser('U' . $index, 'User' . $index, 'ident', 'host', '*', false, false);
        }
        $directory = $this->createStub(ConnectedUsersDirectory::class);
        $directory->method('listConnected')->willReturn($connected);

        return new CountOnlineUsersHandler($directory);
    }
}
