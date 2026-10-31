<?php

declare(strict_types=1);

namespace App\Tests\CRegServ\Adapter\In\Irc\Command;

use App\ChanServ\Application\Port\In\ChannelAdministration;
use App\ChanServ\Application\Port\In\OfficialRegistrationOutcome;
use App\CRegServ\Adapter\In\Irc\Command\OficialCommand;
use App\CRegServ\Application\Security\CRegServPermission;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(OficialCommand::class)]
final class OficialCommandTest extends TestCase
{
    #[Test]
    public function exposesOperOnlyPermissionAndHelpMetadata(): void
    {
        $command = new OficialCommand($this->createStub(ChannelAdministration::class));

        self::assertSame('OFICIAL', $command->getName());
        self::assertSame([], $command->getAliases());
        self::assertSame(2, $command->getMinArgs());
        self::assertSame(9, $command->getOrder());
        self::assertSame('oficial.syntax', $command->getSyntaxKey());
        self::assertSame('oficial.help', $command->getHelpKey());
        self::assertSame('oficial.example', $command->getExampleKey());
        self::assertSame('oficial.short', $command->getShortDescKey());
        self::assertSame([], $command->getSubCommandHelp());
        self::assertTrue($command->isOperOnly());
        self::assertSame(CRegServPermission::OFICIAL, $command->getRequiredPermission());
    }

    #[Test]
    public function registersTheChannelForTheFounderWithTheDescriptionAndAuditsIt(): void
    {
        $channels = $this->createMock(ChannelAdministration::class);
        $channels->expects(self::once())->method('registerOfficial')
            ->with('#Ayuda', 'SitioChat', 'Canal de ayuda', 'NetAdmin', self::anything())
            ->willReturn(OfficialRegistrationOutcome::Registered);
        $harness = new AdministrationCommandHarness();

        $result = new OficialCommand($channels)->execute($harness->context('OFICIAL', ['#Ayuda', 'SitioChat', 'Canal', 'de', 'ayuda']));

        self::assertSame([['oficial.done', ['%channel%' => '#Ayuda', '%nickname%' => 'SitioChat']]], $harness->replies);
        self::assertSame('#Ayuda', $result?->auditData?->target);
        self::assertSame(['founder' => 'SitioChat'], $result->auditData->extra);
    }

    #[Test]
    public function refusesAChannelNameWithoutHash(): void
    {
        $channels = $this->createMock(ChannelAdministration::class);
        $channels->expects(self::never())->method('registerOfficial');
        $harness = new AdministrationCommandHarness();

        self::assertNull(new OficialCommand($channels)->execute($harness->context('OFICIAL', ['Ayuda', 'SitioChat'])));
        self::assertSame([['oficial.invalid_channel', ['%channel%' => 'Ayuda']]], $harness->replies);
    }

    /** @return iterable<string, array{OfficialRegistrationOutcome, string}> */
    public static function refusals(): iterable
    {
        yield 'already registered' => [OfficialRegistrationOutcome::AlreadyRegistered, 'oficial.already_registered'];
        yield 'pending deletion' => [OfficialRegistrationOutcome::PendingDeletion, 'oficial.pending_deletion'];
        yield 'failed' => [OfficialRegistrationOutcome::Failed, 'oficial.failed'];
        yield 'founder not registered' => [OfficialRegistrationOutcome::FounderNotRegistered, 'oficial.founder_not_registered'];
        yield 'founder suspended' => [OfficialRegistrationOutcome::FounderSuspended, 'oficial.founder_suspended'];
    }

    #[Test]
    #[DataProvider('refusals')]
    public function reportsEveryRefusalWithoutAuditing(OfficialRegistrationOutcome $outcome, string $key): void
    {
        $channels = $this->createStub(ChannelAdministration::class);
        $channels->method('registerOfficial')->willReturn($outcome);
        $harness = new AdministrationCommandHarness();

        self::assertNull(new OficialCommand($channels)->execute($harness->context('OFICIAL', ['#Ayuda', 'SitioChat'])));
        self::assertSame([[$key, ['%channel%' => '#Ayuda', '%nickname%' => 'SitioChat']]], $harness->replies);
    }
}
