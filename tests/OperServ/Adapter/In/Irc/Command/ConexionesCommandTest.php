<?php

declare(strict_types=1);

namespace App\Tests\OperServ\Adapter\In\Irc\Command;

use App\OperServ\Adapter\In\Irc\Command\ConexionesCommand;
use App\OperServ\Application\Port\In\OperatorAuthorizationQuery;
use App\OperServ\Application\Security\OperServPermission;
use App\OperServ\Application\UseCase\Clones\ConnectionHosts;
use App\OperServ\Application\UseCase\Clones\ConnectionSessionsHandler;
use App\OperServ\Application\UseCase\Clones\HostSessionCount;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

use function array_column;

#[CoversClass(ConexionesCommand::class)]
#[CoversClass(ConnectionSessionsHandler::class)]
#[CoversClass(HostSessionCount::class)]
#[CoversClass(ConnectionHosts::class)]
final class ConexionesCommandTest extends TestCase
{
    use CloneCommandFixtures;

    #[Test]
    public function exposesOperOnlyPermissionHelpAndSubcommandMetadata(): void
    {
        $command = $this->command();

        self::assertSame('CONEXIONES', $command->getName());
        self::assertSame([], $command->getAliases());
        self::assertSame(1, $command->getMinArgs());
        self::assertSame('conexiones.syntax', $command->getSyntaxKey());
        self::assertSame('conexiones.help', $command->getHelpKey());
        self::assertSame('conexiones.example', $command->getExampleKey());
        self::assertSame(33, $command->getOrder());
        self::assertSame('conexiones.short', $command->getShortDescKey());
        self::assertSame([
            ['name' => 'LIST', 'desc_key' => 'conexiones.list.short', 'help_key' => 'conexiones.list.help', 'syntax_key' => 'conexiones.list.syntax', 'example_key' => 'conexiones.list.example'],
            ['name' => 'VIEW', 'desc_key' => 'conexiones.view.short', 'help_key' => 'conexiones.view.help', 'syntax_key' => 'conexiones.view.syntax', 'example_key' => 'conexiones.view.example'],
        ], $command->getSubCommandHelp());
        self::assertTrue($command->isOperOnly());
        self::assertSame(OperServPermission::CONEXIONES, $command->getRequiredPermission());
        self::assertSame([], $command->getHelpParams());
    }

    #[Test]
    public function listsHostsWithAtLeastTheMinimumSessions(): void
    {
        $harness = new ChannelOverrideCommandHarness();

        $this->command()->execute($harness->context('CONEXIONES', ['list', '2'], $this->createStub(OperatorAuthorizationQuery::class)));

        self::assertSame(['conexiones.list.header', 'conexiones.list.entry', 'conexiones.list.footer'], array_column($harness->replies, 0));
        self::assertSame('2', $harness->replies[0][1]['%minimum%']);
        self::assertSame('2', $harness->replies[1][1]['%count%']);
        self::assertSame('198.51.100.7', $harness->replies[1][1]['%host%']);
    }

    #[Test]
    public function reportsWhenNoHostReachesTheMinimum(): void
    {
        $harness = new ChannelOverrideCommandHarness();

        $this->command()->execute($harness->context('CONEXIONES', ['LIST', '5'], $this->createStub(OperatorAuthorizationQuery::class)));

        self::assertSame(['conexiones.list.empty'], array_column($harness->replies, 0));
        self::assertSame('5', $harness->replies[0][1]['%minimum%']);
    }

    /** @return iterable<string, array{string}> */
    public static function invalidMinimums(): iterable
    {
        yield 'one' => ['1'];
        yield 'zero' => ['0'];
        yield 'negative' => ['-3'];
        yield 'not a number' => ['many'];
    }

    #[Test]
    #[DataProvider('invalidMinimums')]
    public function refusesAMinimumThatIsNotAnIntegerAboveOne(string $minimum): void
    {
        $harness = new ChannelOverrideCommandHarness();

        $this->command()->execute($harness->context('CONEXIONES', ['LIST', $minimum], $this->createStub(OperatorAuthorizationQuery::class)));

        self::assertSame(['conexiones.list.invalid_minimum'], array_column($harness->replies, 0));
    }

    #[Test]
    public function viewsTheSessionCountOfAHost(): void
    {
        $harness = new ChannelOverrideCommandHarness();

        $this->command()->execute($harness->context('CONEXIONES', ['VIEW', '198.51.100.7'], $this->createStub(OperatorAuthorizationQuery::class)));

        self::assertSame(['conexiones.view.result'], array_column($harness->replies, 0));
        self::assertSame('198.51.100.7', $harness->replies[0][1]['%host%']);
        self::assertSame('2', $harness->replies[0][1]['%count%']);
    }

    #[Test]
    public function reportsAHostWithoutSessions(): void
    {
        $harness = new ChannelOverrideCommandHarness();

        $this->command()->execute($harness->context('CONEXIONES', ['view', '203.0.113.200'], $this->createStub(OperatorAuthorizationQuery::class)));

        self::assertSame(['conexiones.view.not_found'], array_column($harness->replies, 0));
        self::assertSame('203.0.113.200', $harness->replies[0][1]['%host%']);
    }

    /** @return iterable<string, array{list<string>, string}> */
    public static function syntaxErrors(): iterable
    {
        yield 'unknown subcommand' => [['SHOW', '2'], 'conexiones.syntax'];
        yield 'list without minimum' => [['LIST'], 'conexiones.list.syntax'];
        yield 'view without host' => [['VIEW', '  '], 'conexiones.view.syntax'];
    }

    /** @param list<string> $arguments */
    #[Test]
    #[DataProvider('syntaxErrors')]
    public function presentsTheMatchingSyntax(array $arguments, string $syntaxKey): void
    {
        $harness = new ChannelOverrideCommandHarness();

        $this->command()->execute($harness->context('CONEXIONES', $arguments, $this->createStub(OperatorAuthorizationQuery::class)));

        self::assertSame([$syntaxKey, 'error.syntax'], array_column($harness->replies, 0));
        self::assertSame($syntaxKey, $harness->replies[1][1]['%syntax%']);
    }

    private function command(): ConexionesCommand
    {
        return new ConexionesCommand(new ConnectionSessionsHandler($this->cloneHosts()));
    }
}
