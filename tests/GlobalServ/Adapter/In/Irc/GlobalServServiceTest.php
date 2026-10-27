<?php

declare(strict_types=1);

namespace App\Tests\GlobalServ\Adapter\In\Irc;

use App\GlobalServ\Adapter\In\Irc\GlobalServAuthorizationCheckerInterface;
use App\GlobalServ\Adapter\In\Irc\GlobalServAuthorizationContextInterface;
use App\GlobalServ\Adapter\In\Irc\GlobalServCommandInterface;
use App\GlobalServ\Adapter\In\Irc\GlobalServCommandRegistry;
use App\GlobalServ\Adapter\In\Irc\GlobalServContext;
use App\GlobalServ\Adapter\In\Irc\GlobalServNotifierInterface;
use App\GlobalServ\Adapter\In\Irc\GlobalServService;
use App\GlobalServ\Application\Port\Out\GlobalServNickAccountPort;
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

#[CoversClass(GlobalServService::class)]
final class GlobalServServiceTest extends TestCase
{
    /**
     * @param list<string> $messages
     */
    private function createNotifier(array &$messages): GlobalServNotifierInterface
    {
        $notifier = $this->createStub(GlobalServNotifierInterface::class);
        $notifier->method('getNick')->willReturn('GlobalServ');
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
                return 'globalserv';
            }

            public function getNickname(): string
            {
                return 'GlobalServ';
            }
        };

        return new ServiceNicknameRegistry([$provider]);
    }

    private function createGrantedChecker(): GlobalServAuthorizationCheckerInterface
    {
        $checker = $this->createStub(GlobalServAuthorizationCheckerInterface::class);
        $checker->method('isGranted')->willReturn(true);

        return $checker;
    }

    private function createDenyingChecker(): GlobalServAuthorizationCheckerInterface
    {
        $checker = $this->createStub(GlobalServAuthorizationCheckerInterface::class);
        $checker->method('isGranted')->willReturn(false);

        return $checker;
    }

    private function createIrcopCommand(): GlobalServCommandInterface
    {
        return new class implements GlobalServCommandInterface {
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
                return 'globalserv.stub';
            }

            public function execute(GlobalServContext $context): null
            {
                throw new RuntimeException('must not be executed');
            }
        };
    }

    /**
     * @param list<string> $messages
     */
    private function createService(
        GlobalServCommandInterface $command,
        array &$messages,
        ?GlobalServAuthorizationCheckerInterface $checker = null,
        ?LoggerInterface $logger = null,
    ): GlobalServService {
        return new GlobalServService(
            commandRegistry: new GlobalServCommandRegistry([$command]),
            notifier: $this->createNotifier($messages),
            translator: $this->createTranslator(),
            serviceNicks: $this->createServiceNicks(),
            authorizationContext: $this->createStub(GlobalServAuthorizationContextInterface::class),
            authorizationChecker: $checker ?? $this->createGrantedChecker(),
            userAccountPort: $this->createStub(GlobalServNickAccountPort::class),
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
