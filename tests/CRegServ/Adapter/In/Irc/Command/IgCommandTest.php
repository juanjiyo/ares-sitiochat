<?php

declare(strict_types=1);

namespace App\Tests\CRegServ\Adapter\In\Irc\Command;

use App\ChanServ\Application\Port\In\ChannelAdministration;
use App\ChanServ\Application\Port\In\ChannelAdministrationOutcome;
use App\CRegServ\Adapter\In\Irc\Command\IgCommand;
use App\CRegServ\Application\Security\CRegServPermission;
use App\Irc\Application\Port\In\Command\CommandOutcome;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(IgCommand::class)]
final class IgCommandTest extends TestCase
{
    #[Test]
    public function exposesOperOnlyPermissionAndHelpMetadata(): void
    {
        $command = new IgCommand($this->createStub(ChannelAdministration::class));

        self::assertSame('IG', $command->getName());
        self::assertSame([], $command->getAliases());
        self::assertSame(1, $command->getMinArgs());
        self::assertSame(20, $command->getOrder());
        self::assertSame('ig.syntax', $command->getSyntaxKey());
        self::assertSame('ig.help', $command->getHelpKey());
        self::assertSame('ig.example', $command->getExampleKey());
        self::assertSame('ig.short', $command->getShortDescKey());
        self::assertSame(['ADD', 'DEL', 'LIST'], array_column($command->getSubCommandHelp(), 'name'));
        self::assertTrue($command->isOperOnly());
        self::assertSame(CRegServPermission::IG, $command->getRequiredPermission());
    }

    /** @return iterable<string, array{string, bool, ChannelAdministrationOutcome, string, bool}> */
    public static function marks(): iterable
    {
        yield 'add' => ['add', true, ChannelAdministrationOutcome::Updated, 'ig.add.done', true];
        yield 'add twice' => ['ADD', true, ChannelAdministrationOutcome::Unchanged, 'ig.add.already', false];
        yield 'del' => ['del', false, ChannelAdministrationOutcome::Updated, 'ig.del.done', true];
        yield 'del unmarked' => ['DEL', false, ChannelAdministrationOutcome::Unchanged, 'ig.del.not_marked', false];
        yield 'not registered' => ['ADD', true, ChannelAdministrationOutcome::NotRegistered, 'ig.not_registered', false];
    }

    #[Test]
    #[DataProvider('marks')]
    public function marksAndUnmarksChannels(string $action, bool $mark, ChannelAdministrationOutcome $outcome, string $reply, bool $audited): void
    {
        $channels = $this->createMock(ChannelAdministration::class);
        $channels->expects(self::once())->method('markInterestGeneral')->with('#Madrid', $mark)->willReturn($outcome);
        $harness = new AdministrationCommandHarness();

        $result = new IgCommand($channels)->execute($harness->context('IG', [$action, '#Madrid']));

        self::assertSame([[$reply, ['%channel%' => '#Madrid']]], $harness->replies);
        if ($audited) {
            self::assertInstanceOf(CommandOutcome::class, $result);
            self::assertSame('#Madrid', $result->auditData?->target);
            self::assertSame(['action' => strtoupper($action)], $result->auditData->extra);
        } else {
            self::assertNull($result);
        }
    }

    #[Test]
    public function listsTheMarkedChannels(): void
    {
        $channels = $this->createStub(ChannelAdministration::class);
        $channels->method('interestGeneralChannels')->willReturnOnConsecutiveCalls(['#Madrid', '#Sevilla'], []);

        $harness = new AdministrationCommandHarness();
        new IgCommand($channels)->execute($harness->context('IG', ['LIST']));
        new IgCommand($channels)->execute($harness->context('IG', ['list']));

        self::assertSame([
            ['ig.list.channels', ['%channels%' => '#Madrid, #Sevilla']],
            ['ig.list.empty', ['%channels%' => '']],
        ], $harness->replies);
    }

    #[Test]
    public function rejectsUnknownActionsAndMissingChannels(): void
    {
        $channels = $this->createMock(ChannelAdministration::class);
        $channels->expects(self::never())->method('markInterestGeneral');
        $harness = new AdministrationCommandHarness();

        new IgCommand($channels)->execute($harness->context('IG', ['ADD']));
        new IgCommand($channels)->execute($harness->context('IG', ['FOO', '#Madrid']));

        self::assertSame(['ig.syntax', 'error.syntax', 'ig.syntax', 'error.syntax'], array_column($harness->replies, 0));
    }
}
