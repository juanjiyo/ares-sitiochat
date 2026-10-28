<?php

declare(strict_types=1);

namespace App\Tests\GlobalServ\Adapter\In\Irc\Command;

use App\GlobalServ\Adapter\In\Irc\Command\GlobalCommand;
use App\GlobalServ\Adapter\In\Irc\Command\HelpCommand;
use App\GlobalServ\Adapter\In\Irc\GlobalServAuthorizationCheckerInterface;
use App\GlobalServ\Adapter\In\Irc\GlobalServCommandInterface;
use App\GlobalServ\Adapter\In\Irc\GlobalServCommandRegistry;
use App\GlobalServ\Adapter\In\Irc\GlobalServContext;
use App\GlobalServ\Adapter\In\Irc\GlobalServNotifierInterface;
use App\GlobalServ\Adapter\In\Irc\Help\UnifiedHelpFormatter;
use App\GlobalServ\Application\UseCase\Global\SendPublicGlobalMessageHandlerInterface;
use App\Irc\Application\Port\In\SenderView;
use App\Irc\Application\Port\In\ServiceNicknameProviderInterface;
use App\Irc\Application\Port\In\ServiceNicknameRegistry;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Contracts\Translation\TranslatorInterface;

use function is_string;

#[CoversClass(HelpCommand::class)]
final class HelpCommandTest extends TestCase
{
    private const string SENDER_UID = '001ABC';

    /**
     * @param array<string, list<string>> $replies
     * @param list<string>                $args
     */
    private function createContext(array &$replies, bool $isOper, string $language = 'en', array $args = []): GlobalServContext
    {
        $notifier = $this->createStub(GlobalServNotifierInterface::class);
        $notifier->method('getNick')->willReturn('GlobalServ');
        $notifier->method('sendMessage')->willReturnCallback(
            static function (string $target, string $message) use (&$replies): void {
                $replies[$target][] = $message;
            },
        );

        $translator = $this->createStub(TranslatorInterface::class);
        $translator->method('trans')->willReturnCallback(
            static function (string $id, array $params = []): string {
                $values = array_values(array_filter($params, static fn (mixed $value): bool => is_string($value)));

                return strtr($id, $params) . ([] === $values ? '' : ' ' . implode(' ', $values));
            },
        );

        return new GlobalServContext(
            sender: new SenderView(self::SENDER_UID, 'Alice', 'i', 'h', 'c', 'ip', isIdentified: true, isOper: $isOper),
            senderAccountId: 7,
            command: 'HELP',
            args: $args,
            notifier: $notifier,
            translator: $translator,
            language: $language,
            registry: $this->createRegistry(),
            serviceNicks: new ServiceNicknameRegistry([$this->createServiceNickProvider()]),
        );
    }

    private function createServiceNickProvider(): ServiceNicknameProviderInterface
    {
        return new class implements ServiceNicknameProviderInterface {
            public function getServiceKey(): string
            {
                return 'globalserv';
            }

            public function getNickname(): string
            {
                return 'GlobalServ';
            }
        };
    }

    private function createRegistry(): GlobalServCommandRegistry
    {
        return new GlobalServCommandRegistry([
            $this->createGlobalCommand(),
        ]);
    }

    private function createGlobalCommand(): GlobalServCommandInterface
    {
        return new GlobalCommand($this->createStub(SendPublicGlobalMessageHandlerInterface::class));
    }

    private function createChecker(bool $granted): GlobalServAuthorizationCheckerInterface
    {
        $checker = $this->createStub(GlobalServAuthorizationCheckerInterface::class);
        $checker->method('isGranted')->willReturn($granted);

        return $checker;
    }

    private function createHelpCommand(bool $granted): HelpCommand
    {
        return new HelpCommand(new UnifiedHelpFormatter(), $this->createChecker($granted));
    }

    #[Test]
    public function metadataIsPublicAndUsesTheHelpKeys(): void
    {
        $command = $this->createHelpCommand(false);

        self::assertSame('HELP', $command->getName());
        self::assertSame(['AYUDA'], $command->getAliases());
        self::assertSame(0, $command->getMinArgs());
        self::assertFalse($command->isOperOnly());
        self::assertNull($command->getRequiredPermission());
        self::assertSame('help.syntax', $command->getSyntaxKey());
        self::assertSame('help.help', $command->getHelpKey());
        self::assertSame('help.short', $command->getShortDescKey());
        self::assertSame([], $command->getSubCommandHelp());
    }

    #[Test]
    public function generalHelpHidesTheIrcopGlobalCommandFromPlainUsers(): void
    {
        $replies = [];
        $command = $this->createHelpCommand(granted: false);
        $context = $this->createContext($replies, isOper: false);

        $command->execute($context);

        $help = $replies[self::SENDER_UID][0] ?? '';
        self::assertStringContainsString('help.intro', $help);
        self::assertStringContainsString('help.general_header', $help);
        self::assertStringNotContainsString('GLOBAL', $help);
        self::assertStringNotContainsString('help.ircop_header', $help);
    }

    #[Test]
    public function generalHelpListsTheGlobalCommandForAuthorizedIrcops(): void
    {
        $replies = [];
        $command = $this->createHelpCommand(granted: true);
        $context = $this->createContext($replies, isOper: true);

        $command->execute($context);

        $help = $replies[self::SENDER_UID][0] ?? '';
        self::assertStringContainsString('help.ircop_header', $help);
        self::assertStringContainsString('GLOBAL', $help);
        self::assertStringContainsString('global.short', $help);
        self::assertStringContainsString('help.general_footer', $help);
    }

    #[Test]
    public function directTopicHelpShowsCommandDetailForAuthorizedIrcops(): void
    {
        $replies = [];
        $command = $this->createHelpCommand(granted: true);
        $context = $this->createContext($replies, isOper: true, args: ['GLOBAL']);

        $command->execute($context);

        $help = $replies[self::SENDER_UID][0] ?? '';
        self::assertStringContainsString('HELP GLOBAL', $help);
        self::assertStringContainsString('global.help', $help);
        self::assertStringContainsString('help.syntax_label', $help);
        self::assertStringContainsString('help.footer', $help);
    }

    #[Test]
    public function directTopicHelpIsDeniedForUnauthorizedUsers(): void
    {
        $replies = [];
        $command = $this->createHelpCommand(granted: false);
        $context = $this->createContext($replies, isOper: false, args: ['GLOBAL']);

        $command->execute($context);

        self::assertStringContainsString('help.unknown_command', $replies[self::SENDER_UID][0] ?? '');
    }

    #[Test]
    public function unknownTopicRepliesWithUnknownCommand(): void
    {
        $replies = [];
        $command = $this->createHelpCommand(granted: true);
        $context = $this->createContext($replies, isOper: true, args: ['NADA']);

        $command->execute($context);

        self::assertStringContainsString('help.unknown_command', $replies[self::SENDER_UID][0] ?? '');
    }
}
