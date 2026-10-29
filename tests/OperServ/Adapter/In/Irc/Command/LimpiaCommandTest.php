<?php

declare(strict_types=1);

namespace App\Tests\OperServ\Adapter\In\Irc\Command;

use App\OperServ\Adapter\In\Irc\Command\ChannelOverridePresenter;
use App\OperServ\Adapter\In\Irc\Command\LimpiaCommand;
use App\OperServ\Application\Port\In\OperatorAuthorizationQuery;
use App\OperServ\Application\Port\Out\NetworkChannel;
use App\OperServ\Application\Port\Out\NetworkChannelLookup;
use App\OperServ\Application\Security\OperServPermission;
use App\OperServ\Application\UseCase\ChannelOverride\SetChannelModes;
use App\OperServ\Application\UseCase\ChannelOverride\SetChannelModesHandler;
use App\Tests\OperServ\Application\UseCase\ChannelOverride\RecordingChannelOverrideActions;
use App\Tests\OperServ\Application\UseCase\ChannelOverride\RecordingCommandAuditRecorder;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(LimpiaCommand::class)]
#[CoversClass(ChannelOverridePresenter::class)]
#[CoversClass(SetChannelModes::class)]
#[CoversClass(SetChannelModesHandler::class)]
final class LimpiaCommandTest extends TestCase
{
    #[Test]
    public function exposesOperOnlyPermissionAndHelpMetadata(): void
    {
        $command = new LimpiaCommand($this->handler(new RecordingChannelOverrideActions()));

        self::assertSame('LIMPIA', $command->getName());
        self::assertSame([], $command->getAliases());
        self::assertSame(1, $command->getMinArgs());
        self::assertSame('limpia.syntax', $command->getSyntaxKey());
        self::assertSame('limpia.help', $command->getHelpKey());
        self::assertSame('limpia.example', $command->getExampleKey());
        self::assertSame(15, $command->getOrder());
        self::assertSame('limpia.short', $command->getShortDescKey());
        self::assertSame([], $command->getSubCommandHelp());
        self::assertTrue($command->isOperOnly());
        self::assertSame(OperServPermission::MODE, $command->getRequiredPermission());
        self::assertSame([], $command->getHelpParams());
    }

    #[Test]
    public function removesRestrictiveModesAndPresentsSuccess(): void
    {
        $network = new RecordingChannelOverrideActions();
        $audit = new RecordingCommandAuditRecorder();
        $harness = new ChannelOverrideCommandHarness();

        new LimpiaCommand($this->handler($network, $audit))
            ->execute($harness->context('LIMPIA', ['#help', 'ignored'], $this->createStub(OperatorAuthorizationQuery::class)));

        self::assertSame([['modes', '#help', ['-imnpst']]], $network->calls);
        self::assertSame('LIMPIA', $audit->records[0]->operation);
        self::assertSame('Oper', $audit->records[0]->actor);
        self::assertCount(1, $harness->replies);
        self::assertSame('limpia.done', $harness->replies[0][0]);
        self::assertSame('#help', $harness->replies[0][1]['%channel%']);
        self::assertSame('-imnpst', $harness->replies[0][1]['%modes%']);
    }

    #[Test]
    public function presentsInvalidChannelName(): void
    {
        $network = new RecordingChannelOverrideActions();
        $harness = new ChannelOverrideCommandHarness();

        new LimpiaCommand($this->handler($network))
            ->execute($harness->context('LIMPIA', ['help'], $this->createStub(OperatorAuthorizationQuery::class)));

        self::assertSame([], $network->calls);
        self::assertSame('channel.invalid_name', $harness->replies[0][0]);
        self::assertSame('help', $harness->replies[0][1]['%channel%']);
    }

    #[Test]
    public function missingSenderDoesNotReachTheUseCase(): void
    {
        $network = new RecordingChannelOverrideActions();
        $harness = new ChannelOverrideCommandHarness();

        new LimpiaCommand($this->handler($network))
            ->execute($harness->context('LIMPIA', ['#help'], $this->createStub(OperatorAuthorizationQuery::class), false));

        self::assertSame([], $network->calls);
        self::assertSame([], $harness->replies);
    }

    private function handler(RecordingChannelOverrideActions $network, ?RecordingCommandAuditRecorder $audit = null): SetChannelModesHandler
    {
        $channels = $this->createStub(NetworkChannelLookup::class);
        $channels->method('findByName')->willReturn(new NetworkChannel('#help', []));

        return new SetChannelModesHandler($channels, $network, $audit ?? new RecordingCommandAuditRecorder());
    }
}
