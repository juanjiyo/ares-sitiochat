<?php

declare(strict_types=1);

namespace App\Tests\AntiSpam\Adapter\In\Irc;

use App\AntiSpam\Adapter\In\Irc\AntiSpamAuthorizationCheckerInterface;
use App\AntiSpam\Adapter\In\Irc\AntiSpamAuthorizationContextInterface;
use App\AntiSpam\Adapter\In\Irc\AntiSpamCommandInterface;
use App\AntiSpam\Adapter\In\Irc\AntiSpamCommandRegistry;
use App\AntiSpam\Adapter\In\Irc\AntiSpamContext;
use App\AntiSpam\Adapter\In\Irc\AntiSpamNotifierInterface;
use App\AntiSpam\Adapter\In\Irc\AntiSpamService;
use App\AntiSpam\Application\Port\Out\AntiSpamNickAccountPort;
use App\Irc\Application\Port\In\SenderView;
use App\Irc\Application\Port\In\ServiceNicknameProviderInterface;
use App\Irc\Application\Port\In\ServiceNicknameRegistry;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use RuntimeException;
use Symfony\Contracts\Translation\TranslatorInterface;

#[CoversClass(AntiSpamService::class)]
final class AntiSpamServiceTest extends TestCase
{
    /**
     * @param list<string> $messages
     */
    private function createNotifier(array &$messages): AntiSpamNotifierInterface
    {
        $notifier = $this->createStub(AntiSpamNotifierInterface::class);
        $notifier->method('getNick')->willReturn('AntiSpam');
        $notifier->method('sendMessage')->willReturnCallback(
            static function (string $target, string $message) use (&$messages): void {
                $messages[] = $message;
            },
        );

        return $notifier;
    }

    private function createTranslator(): TranslatorInterface
    {
        $translator = $this->createStub(TranslatorInterface::class);
        $translator->method('trans')->willReturnCallback(static fn (string $id): string => $id);

        return $translator;
    }

    private function createServiceNicks(): ServiceNicknameRegistry
    {
        $provider = new class implements ServiceNicknameProviderInterface {
            public function getServiceKey(): string
            {
                return 'antispam';
            }

            public function getNickname(): string
            {
                return 'AntiSpam';
            }
        };

        return new ServiceNicknameRegistry([$provider]);
    }

    private function createGrantedChecker(): AntiSpamAuthorizationCheckerInterface
    {
        $checker = $this->createStub(AntiSpamAuthorizationCheckerInterface::class);
        $checker->method('isGranted')->willReturn(true);

        return $checker;
    }

    private function createDenyingChecker(): AntiSpamAuthorizationCheckerInterface
    {
        $checker = $this->createStub(AntiSpamAuthorizationCheckerInterface::class);
        $checker->method('isGranted')->willReturn(false);

        return $checker;
    }

    private function createIrcopCommand(): AntiSpamCommandInterface
    {
        return new class implements AntiSpamCommandInterface {
            public function getName(): string
            {
                return 'STUBOP';
            }

            public function getAliases(): array
            {
                return [];
            }

            public function getMinArgs(): int
            {
                return 1;
            }

            public function getSyntaxKey(): string
            {
                return 'stub.syntax';
            }

            public function getHelpKey(): string
            {
                return 'stub.help';
            }

            public function getOrder(): int
            {
                return 1;
            }

            public function getShortDescKey(): string
            {
                return 'stub.short';
            }

            public function getSubCommandHelp(): array
            {
                return [];
            }

            public function isOperOnly(): bool
            {
                return true;
            }

            public function getRequiredPermission(): string
            {
                return 'antispam.stub';
            }

            public function execute(AntiSpamContext $context): null
            {
                throw new RuntimeException('must not be executed');
            }
        };
    }

    #[Test]
    public function dispatchesToAnAuthorizedCommandAndClearsTheContext(): void
    {
        $messages = [];
        $executed = false;
        $command = $this->createMock(AntiSpamCommandInterface::class);
        $command->method('getName')->willReturn('STUB');
        $command->method('getAliases')->willReturn([]);
        $command->method('getMinArgs')->willReturn(0);
        $command->method('getSyntaxKey')->willReturn('stub.syntax');
        $command->method('getHelpKey')->willReturn('stub.help');
        $command->method('getOrder')->willReturn(1);
        $command->method('getShortDescKey')->willReturn('stub.short');
        $command->method('getSubCommandHelp')->willReturn([]);
        $command->method('isOperOnly')->willReturn(false);
        $command->method('getRequiredPermission')->willReturn('antispam.stub');
        $command->expects(self::once())->method('execute')->willReturnCallback(
            static function (AntiSpamContext $context) use (&$executed): void {
                $executed = true;
            },
        );

        $context = $this->createMock(AntiSpamAuthorizationContextInterface::class);
        $context->expects(self::once())->method('setCurrentUser');
        $context->expects(self::once())->method('clear');

        $service = new AntiSpamService(
            commandRegistry: new AntiSpamCommandRegistry([$command]),
            notifier: $this->createNotifier($messages),
            translator: $this->createTranslator(),
            serviceNicks: $this->createServiceNicks(),
            authorizationContext: $context,
            authorizationChecker: $this->createGrantedChecker(),
            userAccountPort: $this->createStub(AntiSpamNickAccountPort::class),
            logger: new NullLogger(),
        );

        $service->dispatch('STUB', $this->sender());

        self::assertTrue($executed);
    }

    #[Test]
    public function logsAndRethrowsADispatchFailure(): void
    {
        $messages = [];
        $command = $this->createStub(AntiSpamCommandInterface::class);
        $command->method('getName')->willReturn('STUB');
        $command->method('getAliases')->willReturn([]);
        $command->method('getMinArgs')->willReturn(0);
        $command->method('getSyntaxKey')->willReturn('stub.syntax');
        $command->method('getHelpKey')->willReturn('stub.help');
        $command->method('getOrder')->willReturn(1);
        $command->method('getShortDescKey')->willReturn('stub.short');
        $command->method('getSubCommandHelp')->willReturn([]);
        $command->method('isOperOnly')->willReturn(false);
        $command->method('getRequiredPermission')->willReturn(null);
        $command->method('execute')->willThrowException(new RuntimeException('boom'));

        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(self::once())->method('error')
            ->with('AntiSpam dispatch error: boom');

        $service = $this->createService($command, $messages, logger: $logger);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('boom');
        $service->dispatch('STUB', $this->sender());
    }

    /**
     * @param list<string> $messages
     */
    private function createService(
        AntiSpamCommandInterface $command,
        array &$messages,
        ?AntiSpamAuthorizationCheckerInterface $checker = null,
        ?LoggerInterface $logger = null,
    ): AntiSpamService {
        return new AntiSpamService(
            commandRegistry: new AntiSpamCommandRegistry([$command]),
            notifier: $this->createNotifier($messages),
            translator: $this->createTranslator(),
            serviceNicks: $this->createServiceNicks(),
            authorizationContext: $this->createStub(AntiSpamAuthorizationContextInterface::class),
            authorizationChecker: $checker ?? $this->createGrantedChecker(),
            userAccountPort: $this->createStub(AntiSpamNickAccountPort::class),
            logger: $logger ?? new NullLogger(),
        );
    }

    private function sender(bool $isOper = true): SenderView
    {
        return new SenderView('UID1', 'TestUser', 'i', 'h', 'c', 'ip', isIdentified: true, isOper: $isOper);
    }

    #[Test]
    public function emptyCommandLineIsIgnored(): void
    {
        $messages = [];
        $service = $this->createService($this->createIrcopCommand(), $messages);

        $service->dispatch('   ', $this->sender());

        self::assertSame([], $messages);
    }

    #[Test]
    public function unknownCommandRepliesWithUnknownCommandNotice(): void
    {
        $messages = [];
        $service = $this->createService($this->createIrcopCommand(), $messages);

        $service->dispatch('NOCOMANDO', $this->sender());

        self::assertSame(['unknown_command'], $messages);
    }

    #[Test]
    public function ircopCommandWithoutPermissionIsDeniedBeforeExecution(): void
    {
        $messages = [];
        $command = $this->createIrcopCommand();
        $service = $this->createService($command, $messages, $this->createDenyingChecker());

        $service->dispatch('STUBOP argument', $this->sender());

        self::assertSame(['error.permission_denied'], $messages);
    }

    #[Test]
    public function ircopCommandDeniedForNonOperSenderEvenWhenCheckerGrants(): void
    {
        $messages = [];
        $command = $this->createIrcopCommand();
        $service = $this->createService($command, $messages, $this->createGrantedChecker());

        $service->dispatch('STUBOP argument', $this->sender(isOper: false));

        self::assertSame(['error.permission_denied'], $messages);
    }

    #[Test]
    public function tooFewArgumentsRepliesWithSyntaxError(): void
    {
        $messages = [];
        $command = $this->createIrcopCommand();
        $service = $this->createService($command, $messages, $this->createGrantedChecker());

        $service->dispatch('STUBOP', $this->sender());

        self::assertSame(['error.syntax'], $messages);
    }
}
