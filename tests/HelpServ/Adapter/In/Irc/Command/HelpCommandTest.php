<?php

declare(strict_types=1);

namespace App\Tests\HelpServ\Adapter\In\Irc\Command;

use App\HelpServ\Adapter\In\Irc\Command\HelpCommand;
use App\HelpServ\Adapter\In\Irc\Help\UnifiedHelpFormatter;
use App\HelpServ\Adapter\In\Irc\HelpServCommandRegistry;
use App\HelpServ\Adapter\In\Irc\HelpServContext;
use App\HelpServ\Adapter\In\Irc\HelpServNotifierInterface;
use App\Irc\Application\Port\In\SenderView;
use App\Irc\Application\Port\In\ServiceNicknameProviderInterface;
use App\Irc\Application\Port\In\ServiceNicknameRegistry;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Contracts\Translation\TranslatorInterface;

use function get_debug_type;
use function implode;
use function is_scalar;

#[CoversClass(HelpCommand::class)]
final class HelpCommandTest extends TestCase
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
        $translator->method('trans')->willReturnCallback(
            static function (string $id, array $params = []): string {
                $parts = [];
                foreach ($params as $value) {
                    $parts[] = is_scalar($value) ? (string) $value : get_debug_type($value);
                }

                return [] === $parts ? $id : $id . ':' . implode('|', $parts);
            },
        );

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

    /**
     * @param list<string> $args
     */
    private function createContext(
        SenderView $sender,
        string $command,
        array $args,
        HelpServNotifierInterface $notifier,
    ): HelpServContext {
        return new HelpServContext(
            $sender,
            $command,
            $args,
            $notifier,
            $this->createTranslator(),
            'en',
            new HelpServCommandRegistry([new HelpCommand(new UnifiedHelpFormatter())]),
            $this->createServiceNicks(),
        );
    }

    private function sender(): SenderView
    {
        return new SenderView('UID1', 'TestUser', 'i', 'h', 'c', 'ip', isIdentified: true);
    }

    #[Test]
    public function metadataMatchesTheUnifiedHelpConvention(): void
    {
        $help = new HelpCommand(new UnifiedHelpFormatter());

        self::assertSame('HELP', $help->getName());
        self::assertSame(['AYUDA', 'UMODES', 'CMODES', 'BOTS', 'NICK', 'CANAL', 'CREDITOS'], $help->getAliases());
        self::assertSame(0, $help->getMinArgs());
        self::assertSame('help.syntax', $help->getSyntaxKey());
        self::assertSame('help.help', $help->getHelpKey());
        self::assertSame(0, $help->getOrder());
        self::assertSame('help.short', $help->getShortDescKey());
        self::assertFalse($help->isOperOnly());
        self::assertNull($help->getRequiredPermission());
        self::assertSame([], $help->getSubCommandHelp());
    }

    #[Test]
    public function generalHelpListsAllTopics(): void
    {
        $help = new HelpCommand(new UnifiedHelpFormatter());
        $messages = [];
        $context = $this->createContext($this->sender(), 'HELP', [], $this->createNotifier($messages));

        $help->execute($context);

        self::assertCount(1, $messages);
        $output = $messages[0];
        self::assertStringContainsString('help.intro', $output);
        self::assertStringContainsString('help.general_header', $output);
        self::assertStringContainsString('UMODES', $output);
        self::assertStringContainsString('CMODES', $output);
        self::assertStringContainsString('BOTS', $output);
        self::assertStringContainsString('NICK', $output);
        self::assertStringContainsString('CANAL', $output);
        self::assertStringContainsString('CREDITOS', $output);
        self::assertStringContainsString('help.general_footer', $output);
        self::assertStringContainsString('help.footer', $output);
    }

    #[Test]
    public function ayudaWithoutArgumentsListsAllTopics(): void
    {
        $help = new HelpCommand(new UnifiedHelpFormatter());
        $messages = [];
        $context = $this->createContext($this->sender(), 'AYUDA', [], $this->createNotifier($messages));

        $help->execute($context);

        self::assertCount(1, $messages);
        self::assertStringContainsString('help.general_header', $messages[0]);
    }

    /**
     * @return iterable<string, array{0: string}>
     */
    public static function topicProvider(): iterable
    {
        yield 'umodes' => ['UMODES'];
        yield 'cmodes' => ['CMODES'];
        yield 'bots' => ['BOTS'];
        yield 'nick' => ['NICK'];
        yield 'canal' => ['CANAL'];
        yield 'creditos' => ['CREDITOS'];
    }

    #[Test]
    #[DataProvider('topicProvider')]
    public function directTopicCommandRendersItsHelpTextAndSyntax(string $topic): void
    {
        $help = new HelpCommand(new UnifiedHelpFormatter());
        $messages = [];
        $context = $this->createContext($this->sender(), $topic, [], $this->createNotifier($messages));

        $help->execute($context);

        self::assertCount(1, $messages);
        $output = $messages[0];
        self::assertStringContainsString('topic.' . strtolower($topic) . '.help', $output);
        self::assertStringContainsString('help.syntax_label', $output);
        self::assertStringContainsString('topic.' . strtolower($topic) . '.syntax', $output);
        self::assertStringContainsString('help.footer', $output);
    }

    #[Test]
    #[DataProvider('topicProvider')]
    public function helpTopicArgumentRendersItsHelpTextAndSyntax(string $topic): void
    {
        $help = new HelpCommand(new UnifiedHelpFormatter());
        $messages = [];
        $context = $this->createContext($this->sender(), 'HELP', [$topic], $this->createNotifier($messages));

        $help->execute($context);

        self::assertCount(1, $messages);
        $output = $messages[0];
        self::assertStringContainsString('topic.' . strtolower($topic) . '.help', $output);
        self::assertStringContainsString('topic.' . strtolower($topic) . '.syntax', $output);
    }

    #[Test]
    public function unknownTopicRepliesWithTranslatedError(): void
    {
        $help = new HelpCommand(new UnifiedHelpFormatter());
        $messages = [];
        $context = $this->createContext($this->sender(), 'HELP', ['NOSUCH'], $this->createNotifier($messages));

        $help->execute($context);

        self::assertCount(1, $messages);
        self::assertStringContainsString('help.unknown_topic', $messages[0]);
        self::assertStringContainsString('NOSUCH', $messages[0]);
    }
}
