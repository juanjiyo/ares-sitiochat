<?php

declare(strict_types=1);

namespace App\Tests\NickServ\Adapter\In\Irc\Command;

use App\Irc\Application\Port\In\SenderView;
use App\Irc\Application\Port\In\ServiceNicknameProviderInterface;
use App\Irc\Application\Port\In\ServiceNicknameRegistry;
use App\NickServ\Adapter\In\Irc\Command\AutomodeCommand;
use App\NickServ\Adapter\In\Irc\NickServCommandRegistry;
use App\NickServ\Adapter\In\Irc\NickServContext;
use App\NickServ\Adapter\In\Irc\NickServNotifierInterface;
use App\NickServ\Adapter\Out\InMemory\PendingVerificationRegistry;
use App\NickServ\Adapter\Out\InMemory\RecoveryTokenRegistry;
use App\NickServ\Application\Security\NickServPermission;
use App\NickServ\Application\UseCase\AutoMode\SetAutoModes;
use App\NickServ\Application\UseCase\AutoMode\SetAutoModesHandlerInterface;
use App\NickServ\Application\UseCase\AutoMode\SetAutoModesOutcome;
use App\NickServ\Application\UseCase\AutoMode\SetAutoModesResult;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Contracts\Translation\TranslatorInterface;

#[CoversClass(AutomodeCommand::class)]
final class AutomodeCommandTest extends TestCase
{
    #[Test]
    public function exposesCommandMetadata(): void
    {
        $command = new AutomodeCommand($this->createStub(SetAutoModesHandlerInterface::class));

        self::assertSame('AUTOMODE', $command->getName());
        self::assertSame([], $command->getAliases());
        self::assertSame(1, $command->getMinArgs());
        self::assertSame('automode.syntax', $command->getSyntaxKey());
        self::assertSame('automode.help', $command->getHelpKey());
        self::assertSame('automode.short', $command->getShortDescKey());
        self::assertSame('automode.example', $command->getExampleKey());
        self::assertSame([], $command->getSubCommandHelp());
        self::assertSame([], $command->getHelpParams());
        self::assertSame(76, $command->getOrder());
        self::assertTrue($command->isOperOnly());
        self::assertSame(NickServPermission::AUTOMODE, $command->getRequiredPermission());
        self::assertSame('nickserv.automode', NickServPermission::AUTOMODE);
        self::assertContains(NickServPermission::AUTOMODE, NickServPermission::allIrcop());
    }

    #[Test]
    public function rejectsAMissingSenderOrNickWithoutCallingTheUseCase(): void
    {
        $handler = $this->createMock(SetAutoModesHandlerInterface::class);
        $handler->expects(self::never())->method('handle');
        $command = new AutomodeCommand($handler);

        self::assertFalse($command->execute($this->context(null, ['Pedro', '+B']))->success);

        $messages = [];
        self::assertFalse($command->execute($this->context($this->sender(), [], $messages))->success);
        self::assertSame(['error.syntax'], $messages);
    }

    #[Test]
    public function setsTheModesAndReturnsAuditData(): void
    {
        $handler = $this->createMock(SetAutoModesHandlerInterface::class);
        $handler->expects(self::once())->method('handle')
            ->with(new SetAutoModes('Pedro', '+B'))
            ->willReturn(new SetAutoModesResult(SetAutoModesOutcome::Set, 'Pedro', '+B'));

        $messages = [];
        $params = [];
        $outcome = new AutomodeCommand($handler)->execute($this->context($this->sender(), ['Pedro', '+B'], $messages, $params));

        self::assertTrue($outcome->success);
        self::assertSame(['automode.set'], $messages);
        self::assertSame(['%nickname%' => 'Pedro', '%modes%' => '+B'], array_intersect_key($params[0], ['%nickname%' => 1, '%modes%' => 1]));
        self::assertSame('Pedro', $outcome->auditData?->target);
        self::assertSame(['modes' => '+B'], $outcome->auditData?->extra);
    }

    #[Test]
    public function aBareNickClearsTheModes(): void
    {
        $handler = $this->createMock(SetAutoModesHandlerInterface::class);
        $handler->expects(self::once())->method('handle')
            ->with(new SetAutoModes('Pedro', ''))
            ->willReturn(new SetAutoModesResult(SetAutoModesOutcome::Cleared, 'Pedro'));

        $messages = [];
        $outcome = new AutomodeCommand($handler)->execute($this->context($this->sender(), ['Pedro'], $messages));

        self::assertTrue($outcome->success);
        self::assertSame(['automode.cleared'], $messages);
        self::assertSame(['modes' => null], $outcome->auditData?->extra);
    }

    /** @return iterable<string, array{SetAutoModesResult, string, array<string, string>}> */
    public static function refusals(): iterable
    {
        yield 'invalid modes' => [new SetAutoModesResult(SetAutoModesOutcome::InvalidModes, 'Pedro', invalidModes: 'o'), 'automode.invalid_modes', ['%modes%' => 'o']];
        yield 'not registered' => [new SetAutoModesResult(SetAutoModesOutcome::NotRegistered, 'Pedro'), 'automode.not_registered', ['%nickname%' => 'Pedro']];
        yield 'root' => [new SetAutoModesResult(SetAutoModesOutcome::TargetIsRoot, 'Pedro'), 'automode.cannot_modify_oper', []];
        yield 'ircop' => [new SetAutoModesResult(SetAutoModesOutcome::TargetIsIrcop, 'Pedro'), 'automode.cannot_modify_oper', []];
        yield 'service' => [new SetAutoModesResult(SetAutoModesOutcome::TargetIsService, 'Pedro'), 'automode.cannot_modify_oper', []];
    }

    /** @param array<string, string> $expectedParams */
    #[Test]
    #[DataProvider('refusals')]
    public function reportsRefusals(SetAutoModesResult $result, string $expectedKey, array $expectedParams): void
    {
        $handler = $this->createStub(SetAutoModesHandlerInterface::class);
        $handler->method('handle')->willReturn($result);

        $messages = [];
        $params = [];
        $outcome = new AutomodeCommand($handler)->execute($this->context($this->sender(), ['Pedro', '+o'], $messages, $params));

        self::assertFalse($outcome->success);
        self::assertSame([$expectedKey], $messages);
        self::assertSame($expectedParams, array_intersect_key($params[0], $expectedParams));
    }

    /**
     * @param list<string>               $args
     * @param list<string>               $messages
     * @param list<array<string, mixed>> $params
     */
    private function context(?SenderView $sender, array $args, array &$messages = [], array &$params = []): NickServContext
    {
        $notifier = $this->createStub(NickServNotifierInterface::class);
        $notifier->method('getNick')->willReturn('NickServ');
        $notifier->method('sendMessage')->willReturnCallback(static function (string $target, string $message) use (&$messages): void {
            $messages[] = $message;
        });
        $translator = $this->createStub(TranslatorInterface::class);
        $translator->method('trans')->willReturnCallback(static function (string $id, array $parameters = []) use (&$params): string {
            $params[] = $parameters;

            return $id;
        });
        $provider = $this->createStub(ServiceNicknameProviderInterface::class);
        $provider->method('getServiceKey')->willReturn('nickserv');
        $provider->method('getNickname')->willReturn('NickServ');

        return new NickServContext(
            $sender,
            null,
            'AUTOMODE',
            $args,
            $notifier,
            $translator,
            'en',
            'UTC',
            'NOTICE',
            new NickServCommandRegistry([]),
            new PendingVerificationRegistry(),
            new RecoveryTokenRegistry(),
            new ServiceNicknameRegistry([$provider]),
        );
    }

    private function sender(): SenderView
    {
        return new SenderView('OPER1', 'Oper', 'ident', 'oper.test', 'cloak.test', '*', true, true, 'SID1');
    }
}
