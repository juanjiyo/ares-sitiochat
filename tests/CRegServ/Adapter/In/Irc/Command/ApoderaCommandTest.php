<?php

declare(strict_types=1);

namespace App\Tests\CRegServ\Adapter\In\Irc\Command;

use App\ChanServ\Application\Port\In\ChannelAdministration;
use App\ChanServ\Application\Port\In\ChannelAdministrationOutcome;
use App\CRegServ\Adapter\In\Irc\Command\ApoderaCommand;
use App\CRegServ\Application\Security\CRegServPermission;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(ApoderaCommand::class)]
final class ApoderaCommandTest extends TestCase
{
    #[Test]
    public function exposesOperOnlyPermissionAndHelpMetadata(): void
    {
        $command = new ApoderaCommand($this->createStub(ChannelAdministration::class));

        self::assertSame('APODERA', $command->getName());
        self::assertSame([], $command->getAliases());
        self::assertSame(1, $command->getMinArgs());
        self::assertSame(21, $command->getOrder());
        self::assertSame('apodera.syntax', $command->getSyntaxKey());
        self::assertSame('apodera.help', $command->getHelpKey());
        self::assertSame('apodera.example', $command->getExampleKey());
        self::assertSame('apodera.short', $command->getShortDescKey());
        self::assertSame([], $command->getSubCommandHelp());
        self::assertTrue($command->isOperOnly());
        self::assertSame(CRegServPermission::APODERA, $command->getRequiredPermission());
    }

    #[Test]
    public function givesTheChannelToTheNamedNickAndAuditsIt(): void
    {
        $channels = $this->createMock(ChannelAdministration::class);
        $channels->expects(self::once())->method('takeOver')
            ->with('#Madrid', 'SitioChat', 'NetAdmin', self::anything())
            ->willReturn(ChannelAdministrationOutcome::Updated);
        $harness = new AdministrationCommandHarness();

        $result = new ApoderaCommand($channels)->execute($harness->context('APODERA', ['#Madrid', 'SitioChat']));

        self::assertSame([['apodera.done', ['%channel%' => '#Madrid', '%nickname%' => 'SitioChat']]], $harness->replies);
        self::assertSame('#Madrid', $result?->auditData?->target);
        self::assertSame(['founder' => 'SitioChat'], $result->auditData->extra);
    }

    #[Test]
    public function givesTheChannelToTheOperatorWithoutANick(): void
    {
        $channels = $this->createMock(ChannelAdministration::class);
        $channels->expects(self::once())->method('takeOver')
            ->with('#Madrid', 'NetAdmin', 'NetAdmin', self::anything())
            ->willReturn(ChannelAdministrationOutcome::Updated);
        $harness = new AdministrationCommandHarness();

        new ApoderaCommand($channels)->execute($harness->context('APODERA', ['#Madrid']));

        self::assertSame([['apodera.done', ['%channel%' => '#Madrid', '%nickname%' => 'NetAdmin']]], $harness->replies);
    }

    /** @return iterable<string, array{ChannelAdministrationOutcome, string}> */
    public static function refusals(): iterable
    {
        yield 'already founder' => [ChannelAdministrationOutcome::Unchanged, 'apodera.already_founder'];
        yield 'not registered' => [ChannelAdministrationOutcome::NotRegistered, 'apodera.not_registered'];
        yield 'not IG' => [ChannelAdministrationOutcome::NotInterestGeneral, 'apodera.not_ig'];
        yield 'founder not registered' => [ChannelAdministrationOutcome::FounderNotRegistered, 'apodera.founder_not_registered'];
        yield 'founder suspended' => [ChannelAdministrationOutcome::FounderSuspended, 'apodera.founder_suspended'];
    }

    #[Test]
    #[DataProvider('refusals')]
    public function explainsWhyTheChannelWasNotTaken(ChannelAdministrationOutcome $outcome, string $reply): void
    {
        $channels = $this->createStub(ChannelAdministration::class);
        $channels->method('takeOver')->willReturn($outcome);
        $harness = new AdministrationCommandHarness();

        self::assertNull(new ApoderaCommand($channels)->execute($harness->context('APODERA', ['#Madrid', 'Pepe'])));
        self::assertSame([[$reply, ['%channel%' => '#Madrid', '%nickname%' => 'Pepe']]], $harness->replies);
    }
}
