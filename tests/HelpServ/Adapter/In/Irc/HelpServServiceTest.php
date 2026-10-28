<?php

declare(strict_types=1);

namespace App\Tests\HelpServ\Adapter\In\Irc;

use App\HelpServ\Adapter\In\Irc\Command\HelpCommand;
use App\HelpServ\Adapter\In\Irc\Help\UnifiedHelpFormatter;
use App\HelpServ\Adapter\In\Irc\HelpServCommandInterface;
use App\HelpServ\Adapter\In\Irc\HelpServCommandRegistry;
use App\HelpServ\Adapter\In\Irc\HelpServContext;
use App\HelpServ\Adapter\In\Irc\HelpServNotifierInterface;
use App\HelpServ\Adapter\In\Irc\HelpServService;
use App\Irc\Application\Port\In\SenderView;
use App\Irc\Application\Port\In\ServiceNicknameProviderInterface;
use App\Irc\Application\Port\In\ServiceNicknameRegistry;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use RuntimeException;
use Symfony\Contracts\Translation\TranslatorInterface;

#[CoversClass(HelpServService::class)]
final class HelpServServiceTest extends TestCase
{
    /**
     * @param list<string> $messages
     */
    private function createNotifier(array &$messages): HelpServNotifierInterface
    {
        $notifier = $this->createStub(HelpServNotifierInterface::class);
        $notifier->method('getNick')->willReturn('HeLP');
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
                return 'helpserv';
            }

            public function getNickname(): string
            {
                return 'HeLP';
            }
        };

        return new ServiceNicknameRegistry([$provider]);
    }

    private function createService(HelpServCommandInterface $command, HelpServNotifierInterface $notifier): HelpServService
    {
        return new HelpServService(
            commandRegistry: new HelpServCommandRegistry([$command]),
            notifier: $notifier,
            translator: $this->createTranslator(),
            serviceNicks: $this->createServiceNicks(),
        );
    }

    private function sender(): SenderView
    {
        return new SenderView('UID1', 'TestUser', 'i', 'h', 'c', 'ip', isIdentified: true);
    }

    #[Test]
    public function emptyCommandLineIsIgnored(): void
    {
        $messages = [];
        $service = $this->createService(new HelpCommand(new UnifiedHelpFormatter()), $this->createNotifier($messages));

        $service->dispatch('   ', $this->sender());

        self::assertSame([], $messages);
    }

    #[Test]
    public function unknownCommandRepliesWithUnknownCommandNotice(): void
    {
        $messages = [];
        $service = $this->createService(new HelpCommand(new UnifiedHelpFormatter()), $this->createNotifier($messages));

        $service->dispatch('NOCOMANDO', $this->sender());

        self::assertSame(['unknown_command'], $messages);
    }

    #[Test]
    public function commandFailureIsLoggedAndRethrown(): void
    {
        $errors = [];
        $logger = $this->createStub(LoggerInterface::class);
        $logger->method('error')->willReturnCallback(
            static function (string $message, array $context = []) use (&$errors): void {
                $errors[] = [$message, $context];
            },
        );

        $command = new class implements HelpServCommandInterface {
            public function getName(): string
            {
                return 'FALLA';
            }

            public function getAliases(): array
            {
                return [];
            }

            public function getMinArgs(): int
            {
                return 0;
            }

            public function getSyntaxKey(): string
            {
                return 'falla.syntax';
            }

            public function getHelpKey(): string
            {
                return 'falla.help';
            }

            public function getOrder(): int
            {
                return 0;
            }

            public function getShortDescKey(): string
            {
                return 'falla.short';
            }

            public function getSubCommandHelp(): array
            {
                return [];
            }

            public function isOperOnly(): bool
            {
                return false;
            }

            public function getRequiredPermission(): ?string
            {
                return null;
            }

            public function execute(HelpServContext $context): null
            {
                throw new RuntimeException('boom');
            }
        };

        $messages = [];
        $service = new HelpServService(
            commandRegistry: new HelpServCommandRegistry([$command]),
            notifier: $this->createNotifier($messages),
            translator: $this->createTranslator(),
            serviceNicks: $this->createServiceNicks(),
            logger: $logger,
        );

        try {
            $service->dispatch('FALLA', $this->sender());
            self::fail('The dispatch must rethrow the command failure.');
        } catch (RuntimeException $exception) {
            self::assertSame('boom', $exception->getMessage());
        }

        self::assertCount(1, $errors);
        self::assertSame('HelpServ dispatch error: boom', $errors[0][0]);
        self::assertArrayHasKey('exception', $errors[0][1]);
        self::assertSame('UID1', $errors[0][1]['sender']);
        self::assertSame([], $messages);
    }
}
