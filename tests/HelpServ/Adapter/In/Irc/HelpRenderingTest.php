<?php

declare(strict_types=1);

namespace App\Tests\HelpServ\Adapter\In\Irc;

use App\HelpServ\Adapter\In\Irc\Help\Topic;
use App\HelpServ\Adapter\In\Irc\Help\UnifiedHelpFormatter;
use App\HelpServ\Adapter\In\Irc\HelpFormatterContextAdapter;
use App\HelpServ\Adapter\In\Irc\HelpServCommandInterface;
use App\HelpServ\Adapter\In\Irc\HelpServCommandRegistry;
use App\HelpServ\Adapter\In\Irc\HelpServContext;
use App\HelpServ\Adapter\In\Irc\HelpServNotifierInterface;
use App\Irc\Application\Port\In\SenderView;
use App\Irc\Application\Port\In\ServiceNicknameProviderInterface;
use App\Irc\Application\Port\In\ServiceNicknameRegistry;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Contracts\Translation\TranslatorInterface;

use function count;

#[CoversClass(UnifiedHelpFormatter::class)]
#[CoversClass(HelpFormatterContextAdapter::class)]
#[CoversClass(Topic::class)]
#[CoversClass(HelpServCommandRegistry::class)]
#[CoversClass(HelpServContext::class)]
final class HelpRenderingTest extends TestCase
{
    private UnifiedHelpFormatter $formatter;

    protected function setUp(): void
    {
        $this->formatter = new UnifiedHelpFormatter();
    }

    #[Test]
    public function registryFindsCommandsByAliasAndDeduplicates(): void
    {
        $command = $this->createStub(HelpServCommandInterface::class);
        $command->method('getName')->willReturn('HELP');
        $command->method('getAliases')->willReturn(['AYUDA']);

        $registry = new HelpServCommandRegistry([$command]);

        self::assertSame($command, $registry->find('help'));
        self::assertSame($command, $registry->find('ayuda'));
        self::assertNull($registry->find('NOPE'));
        self::assertSame([$command], $registry->all());
    }

    #[Test]
    public function topicsExposeTheirMetadataAndResolveByName(): void
    {
        $topic = Topic::all()[0];

        self::assertSame('UMODES', $topic->getName());
        self::assertSame(1, $topic->getOrder());
        self::assertSame('topic.umodes.short', $topic->getShortDescKey());
        self::assertSame('topic.umodes.help', $topic->getHelpKey());
        self::assertSame('topic.umodes.syntax', $topic->getSyntaxKey());
        self::assertSame([], $topic->getSubCommandHelp());

        self::assertSame($topic->getName(), Topic::fromName('umodes')?->getName());
        self::assertNull(Topic::fromName('NOPE'));
    }

    #[Test]
    public function rendersTheGeneralHelp(): void
    {
        $context = $this->adapter();

        $lines = $this->formatter->renderGeneralHelp($context);

        self::assertSame('help.header', $lines[0]);
        self::assertSame('help.intro', $lines[1]);
        self::assertSame('help.general_header', $lines[3]);
        self::assertContains('help.command_line', $lines);
        self::assertSame('help.general_footer', $lines[count($lines) - 1]);
        self::assertSame('help.footer', $this->formatter->renderFooter($context));
    }

    #[Test]
    public function rendersTheCommandHelp(): void
    {
        $context = $this->adapter();

        $lines = $this->formatter->renderCommandHelp($context, Topic::all()[0]);

        self::assertSame('help.header', $lines[0]);
        self::assertSame('topic.umodes.help', $lines[1]);
        self::assertSame('help.syntax_label', $lines[3]);
        self::assertSame('help.footer', $lines[4]);
    }

    #[Test]
    public function adapterRepliesAndTranslatesThroughTheContext(): void
    {
        $replies = [];
        $context = $this->adapter($replies);

        self::assertCount(6, iterator_to_array($context->getCommandsForGeneralHelp(), false));

        $context->reply('key');
        $context->replyRaw('raw');

        self::assertSame(['key', 'raw'], $replies);
        self::assertSame('key', $context->trans('key'));
    }

    #[Test]
    public function contextStaysSilentWithoutASender(): void
    {
        $replies = [];
        $context = $this->createContext(null, $replies);

        $context->reply('key');
        $context->replyRaw('raw');

        self::assertSame([], $replies);
        self::assertNull($context->getSender());
        self::assertCount(0, $context->getRegistry()->all());
    }

    /**
     * @param list<string> $replies
     */
    private function adapter(array &$replies = []): HelpFormatterContextAdapter
    {
        return new HelpFormatterContextAdapter($this->createContext(new SenderView('001ABC', 'Alice', 'a', 'h', 'c', 'ip'), $replies));
    }

    /**
     * @param list<string> $replies
     */
    private function createContext(?SenderView $sender, array &$replies): HelpServContext
    {
        $notifier = $this->createStub(HelpServNotifierInterface::class);
        $notifier->method('getNick')->willReturn('HeLP');
        $notifier->method('sendMessage')->willReturnCallback(static function (string $target, string $message) use (&$replies): void {
            $replies[] = $message;
        });

        $translator = $this->createStub(TranslatorInterface::class);
        $translator->method('trans')->willReturnCallback(static fn (string $id, array $params = []): string => $id);

        return new HelpServContext(
            sender: $sender,
            command: 'HELP',
            args: [],
            notifier: $notifier,
            translator: $translator,
            language: 'en',
            registry: new HelpServCommandRegistry([]),
            serviceNicks: $this->serviceNicks(),
        );
    }

    private function serviceNicks(): ServiceNicknameRegistry
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
}
