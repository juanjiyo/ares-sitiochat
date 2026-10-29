<?php

declare(strict_types=1);

namespace App\Tests\OperServ\Adapter\In\Irc\Command;

use App\OperServ\Adapter\In\Irc\Command\ChannelOverridePresenter;
use App\OperServ\Adapter\In\Irc\Command\ModeCommand;
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

#[CoversClass(ModeCommand::class)]
#[CoversClass(ChannelOverridePresenter::class)]
#[CoversClass(SetChannelModes::class)]
#[CoversClass(SetChannelModesHandler::class)]
final class ModeCommandTest extends TestCase
{
    #[Test]
    public function exposesOperOnlyPermissionAndHelpMetadata(): void
    {
        $command = new ModeCommand($this->handler(new RecordingChannelOverrideActions()));

        self::assertSame('MODE', $command->getName());
        self::assertSame([], $command->getAliases());
        self::assertSame(2, $command->getMinArgs());
        self::assertSame('mode.syntax', $command->getSyntaxKey());
        self::assertSame('mode.help', $command->getHelpKey());
        self::assertSame('mode.example', $command->getExampleKey());
        self::assertSame(14, $command->getOrder());
        self::assertSame('mode.short', $command->getShortDescKey());
        self::assertSame([], $command->getSubCommandHelp());
        self::assertTrue($command->isOperOnly());
        self::assertSame(OperServPermission::MODE, $command->getRequiredPermission());
        self::assertSame([], $command->getHelpParams());
    }

    #[Test]
    public function appliesModesWithArgumentsAndPresentsSuccess(): void
    {
        $network = new RecordingChannelOverrideActions();
        $audit = new RecordingCommandAuditRecorder();
        $harness = new ChannelOverrideCommandHarness();

        new ModeCommand($this->handler($network, audit: $audit))
            ->execute($harness->context('MODE', ['#help', '+kl', 'secret', '10'], $this->createStub(OperatorAuthorizationQuery::class)));

        self::assertSame([['modes', '#help', ['+kl', 'secret', '10']]], $network->calls);
        self::assertSame('MODE', $audit->records[0]->operation);
        self::assertSame('Oper', $audit->records[0]->actor);
        self::assertCount(1, $harness->replies);
        self::assertSame('mode.done', $harness->replies[0][0]);
        self::assertSame('#help', $harness->replies[0][1]['%channel%']);
        self::assertSame('+kl secret 10', $harness->replies[0][1]['%modes%']);
    }

    #[Test]
    public function presentsInvalidModesWithoutNetworkEffect(): void
    {
        $network = new RecordingChannelOverrideActions();
        $harness = new ChannelOverrideCommandHarness();

        new ModeCommand($this->handler($network))
            ->execute($harness->context('MODE', ['#help', 'm'], $this->createStub(OperatorAuthorizationQuery::class)));

        self::assertSame([], $network->calls);
        self::assertSame('mode.invalid_modes', $harness->replies[0][0]);
        self::assertSame('m', $harness->replies[0][1]['%modes%']);
    }

    #[Test]
    public function presentsMissingChannel(): void
    {
        $harness = new ChannelOverrideCommandHarness();

        new ModeCommand($this->handler(new RecordingChannelOverrideActions(), false))
            ->execute($harness->context('MODE', ['#gone', '+m'], $this->createStub(OperatorAuthorizationQuery::class)));

        self::assertSame('channel.not_found', $harness->replies[0][0]);
        self::assertSame('#gone', $harness->replies[0][1]['%channel%']);
    }

    #[Test]
    public function missingSenderDoesNotReachTheUseCase(): void
    {
        $network = new RecordingChannelOverrideActions();
        $harness = new ChannelOverrideCommandHarness();

        new ModeCommand($this->handler($network))
            ->execute($harness->context('MODE', ['#help', '+m'], $this->createStub(OperatorAuthorizationQuery::class), false));

        self::assertSame([], $network->calls);
        self::assertSame([], $harness->replies);
    }

    private function handler(RecordingChannelOverrideActions $network, bool $channelExists = true, ?RecordingCommandAuditRecorder $audit = null): SetChannelModesHandler
    {
        $channels = $this->createStub(NetworkChannelLookup::class);
        $channels->method('findByName')->willReturn($channelExists ? new NetworkChannel('#help', []) : null);

        return new SetChannelModesHandler($channels, $network, $audit ?? new RecordingCommandAuditRecorder());
    }
}
