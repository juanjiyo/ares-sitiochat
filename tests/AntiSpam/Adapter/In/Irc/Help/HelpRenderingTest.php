<?php

declare(strict_types=1);

namespace App\Tests\AntiSpam\Adapter\In\Irc\Help;

use App\AntiSpam\Adapter\In\Irc\AntiSpamAuthorizationCheckerInterface;
use App\AntiSpam\Adapter\In\Irc\AntiSpamCommandInterface;
use App\AntiSpam\Adapter\In\Irc\AntiSpamCommandRegistry;
use App\AntiSpam\Adapter\In\Irc\AntiSpamContext;
use App\AntiSpam\Adapter\In\Irc\AntiSpamNotifierInterface;
use App\AntiSpam\Adapter\In\Irc\Help\UnifiedHelpFormatter;
use App\AntiSpam\Adapter\In\Irc\HelpFormatterContextAdapter;
use App\Irc\Application\Port\In\SenderView;
use App\Irc\Application\Port\In\ServiceNicknameProviderInterface;
use App\Irc\Application\Port\In\ServiceNicknameRegistry;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Contracts\Translation\TranslatorInterface;

#[CoversClass(UnifiedHelpFormatter::class)]
#[CoversClass(HelpFormatterContextAdapter::class)]
final class HelpRenderingTest extends TestCase
{
    private UnifiedHelpFormatter $formatter;

    protected function setUp(): void
    {
        $this->formatter = new UnifiedHelpFormatter();
    }

    #[Test]
    public function rendersTheHeaderAndTheFooter(): void
    {
        $context = $this->adapter([], granted: false, isOper: false);

        self::assertSame('help.header', $context->trans('help.header', ['title' => 'AntiSpam', 'separator' => '────────']));
        self::assertSame('help.footer', $this->formatter->renderFooter($context));
    }

    #[Test]
    public function generalHelpListsPublicCommandsAndHidesTheHelpTopic(): void
    {
        $public = $this->command('PUBLIC', 1, null, operOnly: false);
        $context = $this->adapter([$public, $this->command('HELP', 99, null, operOnly: false)], granted: false, isOper: false);

        $lines = $this->formatter->renderGeneralHelp($context);

        self::assertContains('help.command_line', $lines);
        self::assertNotContains('help.ircop_header', $lines);
        self::assertCount(1, array_filter($lines, static fn (string $line): bool => 'help.command_line' === $line));
    }

    #[Test]
    public function generalHelpAddsTheIrcopSectionForAuthorizedSenders(): void
    {
        $ircop = $this->command('GLOBAL', 1, 'antispam.global', operOnly: true);
        $context = $this->adapter([$ircop], granted: true, isOper: true);

        $lines = $this->formatter->renderGeneralHelp($context);

        self::assertContains('help.ircop_header', $lines);
        self::assertContains('help.command_line', $lines);
        self::assertContains('help.general_footer', $lines);
    }

    #[Test]
    public function commandHelpRendersTitleHelpAndSyntax(): void
    {
        $command = $this->command('GLOBAL', 1, 'antispam.global', operOnly: true);
        $context = $this->adapter([$command], granted: true, isOper: true);

        $lines = $this->formatter->renderCommandHelp($context, $command);

        self::assertSame('help.header', $lines[0]);
        self::assertSame('global.help', $lines[1]);
        self::assertSame('help.syntax_label', $lines[3]);
        self::assertSame('help.footer', $lines[4]);
    }

    #[Test]
    public function adapterExposesCommandsAndReplies(): void
    {
        $replies = [];
        $command = $this->command('PUBLIC', 1, null, operOnly: false);
        $context = $this->adapter([$command], granted: false, isOper: false, replies: $replies);

        self::assertSame([$command], iterator_to_array($context->getCommandsForGeneralHelp(), false));
        self::assertTrue($context->shouldShowCommandInGeneralHelp($command));
        self::assertTrue($context->canViewCommandInHelp($command));
        self::assertFalse($context->hasIrcopAccess());

        $context->reply('key');
        $context->replyRaw('raw');

        self::assertSame(['key', 'raw'], $replies);
    }

    #[Test]
    public function adapterHidesIrcopCommandsWithoutPermission(): void
    {
        $ircop = $this->command('GLOBAL', 1, 'antispam.global', operOnly: true);
        $context = $this->adapter([$ircop], granted: false, isOper: true);

        self::assertFalse($context->shouldShowCommandInGeneralHelp($ircop));
        self::assertFalse($context->canViewCommandInHelp($ircop));
        self::assertSame([], iterator_to_array($context->getIrcopCommands(), false));
        self::assertFalse($context->hasIrcopAccess());
    }

    #[Test]
    public function adapterAllowsDetailedHelpForGrantedIrcopCommands(): void
    {
        $ircop = $this->command('GLOBAL', 1, 'antispam.global', operOnly: true);
        $context = $this->adapter([$ircop], granted: true, isOper: true);

        self::assertTrue($context->canViewCommandInHelp($ircop));
        self::assertSame([$ircop], iterator_to_array($context->getIrcopCommands(), false));
        self::assertTrue($context->hasIrcopAccess());
    }

    #[Test]
    public function adapterShowsOperOnlyCommandsToIrcOperatorsWithoutPermission(): void
    {
        $operOnly = $this->command('OPERONLY', 1, null, operOnly: true);
        $context = $this->adapter([$operOnly], granted: false, isOper: true);

        self::assertTrue($context->shouldShowCommandInGeneralHelp($operOnly));
        self::assertTrue($context->canViewCommandInHelp($operOnly));
    }

    /**
     * @param list<AntiSpamCommandInterface> $commands
     * @param list<string>                     $replies
     */
    private function adapter(array $commands, bool $granted, bool $isOper, array &$replies = []): HelpFormatterContextAdapter
    {
        $notifier = $this->createStub(AntiSpamNotifierInterface::class);
        $notifier->method('getNick')->willReturn('AntiSpam');
        $notifier->method('sendMessage')->willReturnCallback(static function (string $target, string $message) use (&$replies): void {
            $replies[] = $message;
        });

        $translator = $this->createStub(TranslatorInterface::class);
        $translator->method('trans')->willReturnCallback(
            static fn (string $id, array $params = []): string => $id,
        );

        $context = new AntiSpamContext(
            sender: new SenderView('001ABC', 'Alice', 'a', 'h', 'c', 'ip', isIdentified: true, isOper: $isOper),
            senderAccountId: 7,
            command: 'HELP',
            args: [],
            notifier: $notifier,
            translator: $translator,
            language: 'en',
            registry: new AntiSpamCommandRegistry($commands),
            serviceNicks: $this->serviceNicks(),
        );

        $checker = $this->createStub(AntiSpamAuthorizationCheckerInterface::class);
        $checker->method('isGranted')->willReturn($granted);

        return new HelpFormatterContextAdapter($context, $checker);
    }

    private function command(string $name, int $order, ?string $permission, bool $operOnly): AntiSpamCommandInterface
    {
        $command = $this->createStub(AntiSpamCommandInterface::class);
        $command->method('getName')->willReturn($name);
        $command->method('getOrder')->willReturn($order);
        $command->method('getShortDescKey')->willReturn(strtolower($name) . '.short');
        $command->method('getSyntaxKey')->willReturn(strtolower($name) . '.syntax');
        $command->method('getHelpKey')->willReturn(strtolower($name) . '.help');
        $command->method('getSubCommandHelp')->willReturn([]);
        $command->method('getRequiredPermission')->willReturn($permission);
        $command->method('isOperOnly')->willReturn($operOnly);
        $command->method('getAliases')->willReturn([]);

        return $command;
    }

    private function serviceNicks(): ServiceNicknameRegistry
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
}
