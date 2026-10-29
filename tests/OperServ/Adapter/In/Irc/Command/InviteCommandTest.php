<?php

declare(strict_types=1);

namespace App\Tests\OperServ\Adapter\In\Irc\Command;

use App\OperServ\Adapter\In\Irc\Command\ChannelOverridePresenter;
use App\OperServ\Adapter\In\Irc\Command\InviteCommand;
use App\OperServ\Application\Port\In\OperatorAuthorizationQuery;
use App\OperServ\Application\Port\Out\NetworkChannel;
use App\OperServ\Application\Port\Out\NetworkChannelLookup;
use App\OperServ\Application\Security\OperServPermission;
use App\OperServ\Application\UseCase\ChannelOverride\InviteOperator;
use App\OperServ\Application\UseCase\ChannelOverride\InviteOperatorHandler;
use App\Tests\OperServ\Application\UseCase\ChannelOverride\RecordingChannelOverrideActions;
use App\Tests\OperServ\Application\UseCase\ChannelOverride\RecordingCommandAuditRecorder;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(InviteCommand::class)]
#[CoversClass(ChannelOverridePresenter::class)]
#[CoversClass(InviteOperator::class)]
#[CoversClass(InviteOperatorHandler::class)]
final class InviteCommandTest extends TestCase
{
    #[Test]
    public function exposesOperOnlyPermissionAndHelpMetadata(): void
    {
        $command = new InviteCommand($this->handler(new RecordingChannelOverrideActions()));

        self::assertSame('INVITE', $command->getName());
        self::assertSame([], $command->getAliases());
        self::assertSame(1, $command->getMinArgs());
        self::assertSame('invite.syntax', $command->getSyntaxKey());
        self::assertSame('invite.help', $command->getHelpKey());
        self::assertSame('invite.example', $command->getExampleKey());
        self::assertSame(16, $command->getOrder());
        self::assertSame('invite.short', $command->getShortDescKey());
        self::assertSame([], $command->getSubCommandHelp());
        self::assertTrue($command->isOperOnly());
        self::assertSame(OperServPermission::INVITE, $command->getRequiredPermission());
        self::assertSame([], $command->getHelpParams());
    }

    #[Test]
    public function invitesCallerAndPresentsSuccess(): void
    {
        $network = new RecordingChannelOverrideActions();
        $audit = new RecordingCommandAuditRecorder();
        $harness = new ChannelOverrideCommandHarness();

        new InviteCommand($this->handler($network, audit: $audit))
            ->execute($harness->context('INVITE', ['#help'], $this->createStub(OperatorAuthorizationQuery::class)));

        self::assertSame([['invite', '#help', ['UIDOPER']]], $network->calls);
        self::assertSame('INVITE', $audit->records[0]->operation);
        self::assertSame('Oper', $audit->records[0]->actor);
        self::assertCount(1, $harness->replies);
        self::assertSame('invite.done', $harness->replies[0][0]);
        self::assertSame('#help', $harness->replies[0][1]['%channel%']);
    }

    #[Test]
    public function presentsCallerAlreadyOnChannel(): void
    {
        $network = new RecordingChannelOverrideActions();
        $harness = new ChannelOverrideCommandHarness();

        new InviteCommand($this->handler($network, true))
            ->execute($harness->context('INVITE', ['#help'], $this->createStub(OperatorAuthorizationQuery::class)));

        self::assertSame([], $network->calls);
        self::assertSame('invite.already_on_channel', $harness->replies[0][0]);
        self::assertSame('#help', $harness->replies[0][1]['%channel%']);
    }

    #[Test]
    public function missingSenderDoesNotReachTheUseCase(): void
    {
        $network = new RecordingChannelOverrideActions();
        $harness = new ChannelOverrideCommandHarness();

        new InviteCommand($this->handler($network))
            ->execute($harness->context('INVITE', ['#help'], $this->createStub(OperatorAuthorizationQuery::class), false));

        self::assertSame([], $network->calls);
        self::assertSame([], $harness->replies);
    }

    private function handler(RecordingChannelOverrideActions $network, bool $inside = false, ?RecordingCommandAuditRecorder $audit = null): InviteOperatorHandler
    {
        $channels = $this->createStub(NetworkChannelLookup::class);
        $channels->method('findByName')->willReturn(new NetworkChannel('#help', $inside ? ['UIDOPER'] : []));

        return new InviteOperatorHandler($channels, $network, $audit ?? new RecordingCommandAuditRecorder());
    }
}
