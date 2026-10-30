<?php

declare(strict_types=1);

namespace App\Tests\CRegServ\Adapter\In\Irc\Help;

use App\CRegServ\Adapter\In\Irc\Help\HelpableCommandInterface;
use App\CRegServ\Adapter\In\Irc\Help\HelpFormatterContextInterface;
use App\CRegServ\Adapter\In\Irc\Help\UnifiedHelpFormatter;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Yaml\Yaml;

use function count;
use function dirname;
use function in_array;
use function is_array;
use function is_string;
use function sprintf;

use const STR_PAD_LEFT;

#[CoversClass(UnifiedHelpFormatter::class)]
final class UnifiedHelpFormatterTest extends TestCase
{
    private const array LOCALES = ['ca', 'de', 'el', 'en', 'es', 'eu', 'fr', 'gl', 'it', 'nl', 'pl', 'pt', 'ro', 'tr'];

    private const array ALLOWED_HELP_COLORS = ['03', '04', '07', '10', '14'];

    #[Test]
    public function rendersFilteredGeneralHelpAndSortedIrcopSection(): void
    {
        $context = new CRegServHelpFormatterContext(
            commands: [
                new CRegServHelpableCommand('HIDDEN', 2),
                new CRegServHelpableCommand('VISIBLE', 1),
                new CRegServHelpableCommand('HELP', 0),
            ],
            ircopCommands: [
                new CRegServHelpableCommand('SAREGISTER', 9),
                new CRegServHelpableCommand('APROBAR', 1),
            ],
            visibleCommands: ['VISIBLE'],
            ircopAccess: true,
        );

        $lines = new UnifiedHelpFormatter('TestNet')->renderGeneralHelp($context);

        self::assertSame([
            'translated:help.header_general',
            'translated:help.intro',
            ' ',
            'translated:help.general_header',
            'translated:help.command_line',
            ' ',
            'translated:help.ircop_header',
            'translated:help.command_line',
            'translated:help.command_line',
            ' ',
            'translated:help.warning',
            ' ',
            'translated:help.footer_general',
        ], $lines);
        self::assertSame([], $context->replies);
        self::assertSame([], $context->rawReplies);

        $renderedCommands = [];
        foreach ($context->translations as $reply) {
            if ('help.command_line' !== $reply['key']) {
                continue;
            }

            self::assertIsString($reply['params']['command']);
            $renderedCommands[] = $reply['params']['command'];
        }
        self::assertSame([
            'VISIBLE      ',
            'APROBAR      ',
            'SAREGISTER   ',
        ], $renderedCommands);
    }

    #[Test]
    public function rendersEveryIrcopGroupCommandUnderASingleOperatorsHeader(): void
    {
        $groups = [
            ['commands' => ['APROBAR', 'DENEGAR'], 'admin' => true],
            ['commands' => ['PENDIENTES', 'DENEGADOS', 'DROP'], 'admin' => true],
            ['commands' => ['SAREGISTER'], 'admin' => true],
        ];
        $commandNames = array_merge(...array_column($groups, 'commands'));
        $commands = [];
        foreach ($commandNames as $order => $name) {
            $commands[] = new CRegServHelpableCommand($name, $order);
        }
        $context = new CRegServHelpFormatterContext(
            ircopCommands: $commands,
            ircopAccess: true,
            helpGroups: $groups,
        );

        $lines = new UnifiedHelpFormatter('TestNet')->renderGeneralHelp($context);

        $translatedKeys = array_column($context->translations, 'key');
        self::assertCount(1, array_keys($translatedKeys, 'help.ircop_header', true));
        self::assertNotContains('translated:help.subgroup_header', $lines);

        $renderedCommands = [];
        foreach ($context->translations as $reply) {
            if ('help.command_line' !== $reply['key']) {
                continue;
            }

            self::assertIsString($reply['params']['command']);
            $renderedCommands[] = trim($reply['params']['command']);
        }
        self::assertSame($commandNames, $renderedCommands);
    }

    #[Test]
    public function rendersUngroupedCommandsBeforeTheWarningWithoutAdminCommands(): void
    {
        $context = new CRegServHelpFormatterContext(
            commands: [new CRegServHelpableCommand('VISIBLE', 1)],
            visibleCommands: ['VISIBLE'],
            helpGroups: [
                ['commands' => ['NOTHERE'], 'admin' => false],
            ],
        );

        $lines = new UnifiedHelpFormatter('TestNet')->renderGeneralHelp($context);

        self::assertSame('translated:help.general_header', $lines[3]);
        self::assertSame('translated:help.command_line', $lines[4]);
        self::assertSame('translated:help.warning', $lines[6]);
        self::assertSame('translated:help.footer_general', $lines[8]);
        self::assertCount(9, $lines);
        self::assertNotContains('translated:help.ircop_header', $lines);
    }

    #[Test]
    public function rendersCommandHelpWithParametersAndOptions(): void
    {
        $context = new CRegServHelpFormatterContext();

        $lines = new UnifiedHelpFormatter('TestNet')->renderCommandHelp($context, new CRegServHelpableCommand('SET', 1, [[
            'name' => 'EMAIL',
            'desc_key' => 'set.email.short',
            'help_key' => 'set.email.help',
            'syntax_key' => 'set.email.syntax',
        ]], ['command' => 'SET']));

        self::assertSame([
            'translated:help.command_header',
            'translated:set.help',
            ' ',
            'translated:help.options_header',
            'translated:help.subcommand_line',
            ' ',
            'translated:help.set_sub_footer',
            ' ',
            'translated:help.syntax_label',
            ' ',
            'translated:help.footer_command',
        ], $lines);
        self::assertSame([], $context->rawReplies);
    }

    #[Test]
    public function rendersSubcommandHelpWithAndWithoutOptions(): void
    {
        $formatter = new UnifiedHelpFormatter('TestNet');

        $withOptions = $formatter->renderSubCommandHelp(new CRegServHelpFormatterContext(), 'SET', [
            'name' => 'EMAIL',
            'help_key' => 'set.email.help',
            'syntax_key' => 'set.email.syntax',
            'options_key' => 'set.email.options',
        ]);
        self::assertSame([
            'translated:help.command_header',
            'translated:set.email.help',
            ' ',
            'translated:set.email.options',
            ' ',
            'translated:help.syntax_label',
            ' ',
            'translated:help.footer_command',
        ], $withOptions);

        $withoutOptions = $formatter->renderSubCommandHelp(new CRegServHelpFormatterContext(), 'SET', [
            'name' => 'EMAIL',
            'help_key' => 'set.email.help',
            'syntax_key' => 'set.email.syntax',
        ]);
        self::assertSame([
            'translated:help.command_header',
            'translated:set.email.help',
            ' ',
            'translated:help.syntax_label',
            ' ',
            'translated:help.footer_command',
        ], $withoutOptions);
    }

    #[Test]
    public function rendersTheExampleLineOnlyForCommandsExposingAnExampleKey(): void
    {
        $formatter = new UnifiedHelpFormatter('TestNet');

        $withExampleContext = new CRegServHelpFormatterContext();
        $withExample = $formatter->renderCommandHelp($withExampleContext, new CRegServHelpableCommandWithExample('CANCEL', 3));

        self::assertSame([
            'translated:help.command_header',
            'translated:cancel.help',
            ' ',
            'translated:help.syntax_label',
            ' ',
            'translated:help.example_label',
            ' ',
            'translated:help.footer_command',
        ], $withExample);

        $exampleLabels = array_values(array_filter(
            $withExampleContext->translations,
            static fn (array $entry): bool => 'help.example_label' === $entry['key'],
        ));
        self::assertCount(1, $exampleLabels);
        self::assertSame(['example' => 'translated:cancel.example'], $exampleLabels[0]['params']);

        $withoutExampleContext = new CRegServHelpFormatterContext();
        $withoutExample = $formatter->renderCommandHelp($withoutExampleContext, new CRegServHelpableCommand('REGISTRA', 1));

        self::assertNotContains('translated:help.example_label', $withoutExample);
        self::assertSame('translated:help.syntax_label', $withoutExample[count($withoutExample) - 3]);
    }

    #[Test]
    public function definesHeaderTemplatesAndPlaceholdersInEveryLocale(): void
    {
        $context = new CRegServHelpFormatterContext();
        $lines = new UnifiedHelpFormatter('TestNet')->renderGeneralHelp($context);

        self::assertSame('translated:help.header_general', $lines[0]);
        self::assertSame([], $context->replies);
        self::assertSame([], $context->rawReplies);

        self::assertSame([
            'key' => 'help.header_general',
            'params' => ['network' => 'TestNet'],
        ], $context->translations[0]);

        $commandContext = new CRegServHelpFormatterContext();
        new UnifiedHelpFormatter('TestNet')->renderCommandHelp($commandContext, new CRegServHelpableCommand('REGISTRA', 1));

        self::assertSame([
            'key' => 'help.command_header',
            'params' => ['command' => 'REGISTRA'],
        ], $commandContext->translations[0]);

        foreach (self::LOCALES as $locale) {
            $help = $this->loadHelpCatalog($locale);
            self::assertSame("\x02\x0303%bot%\x03\x0F - \x0307%network%\x03", $help['header_general'] ?? null, $locale);
            self::assertIsString($help['command_header'], $locale);
            self::assertStringStartsWith("\x02\x0303%command%\x03\x0F - ", $help['command_header'], $locale);
            $commandHeaderTail = (string) substr($help['command_header'], 12);
            self::assertStringContainsString('%command%', $commandHeaderTail, $locale);
        }
    }

    #[Test]
    public function allLocaleHelpTemplatesUseOnlyCanonicalStructuralColors(): void
    {
        foreach (self::LOCALES as $locale) {
            $help = $this->loadHelpCatalog($locale);
            $this->assertHelpColorsAllowed($help, $locale);

            foreach (['general_header', 'ircop_header', 'options_header'] as $section) {
                self::assertIsString($help[$section], $locale);
                self::assertStringStartsWith("\x02", $help[$section], $locale);
                self::assertStringEndsWith("\x02", $help[$section], $locale);
                self::assertStringNotContainsString('%', $help[$section], $locale);
            }
            foreach (['command_line', 'subcommand_line'] as $row) {
                self::assertSame("   \x0303%command%\x03\x0F%description%", $help[$row], $locale);
            }
            self::assertIsString($help['set_sub_footer'], $locale);
            self::assertStringContainsString('/msg %bot% HELP %command%', $help['set_sub_footer'], $locale);
            foreach (['syntax_label', 'example_label'] as $label) {
                self::assertIsString($help[$label], $locale);
                self::assertStringStartsWith("\x02", $help[$label], $locale);
                self::assertStringContainsString("\n    \x0303", $help[$label], $locale);
            }
            $syntaxLabel = $help['syntax_label'];
            self::assertIsString($syntaxLabel, $locale);
            self::assertStringContainsString('/msg %bot% %syntax%', $syntaxLabel, $locale);
            self::assertIsString($help['warning'], $locale);
            self::assertStringStartsWith("\x02\x0304", $help['warning'], $locale);
            foreach (['footer_general', 'footer_command'] as $end) {
                self::assertIsString($help[$end], $locale);
                self::assertStringStartsWith("\x02", $help[$end], $locale);
                self::assertStringEndsWith("\x02", $help[$end], $locale);
            }
            self::assertIsString($help['intro'], $locale);
            self::assertStringContainsString('/msg %bot% HELP', $help['intro'], $locale);
            self::assertStringContainsString("\n\n", $help['intro'], $locale);
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function loadHelpCatalog(string $locale): array
    {
        $path = dirname(__DIR__, 6) . '/translations/cregserv.' . $locale . '.yaml';
        $catalog = $this->requireStringKeyedArray(Yaml::parseFile($path), $locale . ' catalog must parse as a mapping');

        return $this->requireStringKeyedArray($catalog['help'] ?? null, $locale . ' must define the help subtree');
    }

    /**
     * @return array<string, mixed>
     */
    private function requireStringKeyedArray(mixed $value, string $message): array
    {
        self::assertIsArray($value, $message);

        $entries = [];
        foreach ($value as $key => $entry) {
            self::assertIsString($key, $message);
            $entries[$key] = $entry;
        }

        return $entries;
    }

    /**
     * @param array<string|int, mixed> $values
     */
    private function assertHelpColorsAllowed(array $values, string $locale, string $path = 'help'): void
    {
        foreach ($values as $key => $value) {
            $currentPath = $path . '.' . $key;
            if (is_array($value)) {
                $this->assertHelpColorsAllowed($value, $locale, $currentPath);
                continue;
            }
            if (!is_string($value)) {
                continue;
            }

            preg_match_all('/\x03([0-9]{1,2})/', $value, $matches);
            foreach ($matches[1] as $color) {
                $color = str_pad($color, 2, '0', STR_PAD_LEFT);
                self::assertContains($color, self::ALLOWED_HELP_COLORS, sprintf('%s contains forbidden structural color %s', $locale . ':' . $currentPath, $color));
            }
        }
    }
}

final class CRegServHelpFormatterContext implements HelpFormatterContextInterface
{
    /** @var list<array{key: string, params: array<string, mixed>}> */
    public array $replies = [];

    /** @var list<string> */
    public array $rawReplies = [];

    /** @var list<array{key: string, params: array<string, mixed>}> */
    public array $translations = [];

    /**
     * @param list<HelpableCommandInterface>                   $commands
     * @param list<HelpableCommandInterface>                   $ircopCommands
     * @param list<string>                                     $visibleCommands
     * @param list<array{commands: list<string>, admin: bool}> $helpGroups
     */
    public function __construct(
        private readonly array $commands = [],
        private readonly array $ircopCommands = [],
        private readonly array $visibleCommands = [],
        private readonly bool $ircopAccess = false,
        private readonly array $helpGroups = [],
    ) {}

    public function reply(string $key, array $params = []): void
    {
        $this->replies[] = ['key' => $key, 'params' => $params];
    }

    public function replyRaw(string $message): void
    {
        $this->rawReplies[] = $message;
    }

    public function trans(string $key, array $params = []): string
    {
        $this->translations[] = ['key' => $key, 'params' => $params];

        return 'translated:' . $key;
    }

    public function getCommandsForGeneralHelp(): iterable
    {
        return $this->commands;
    }

    public function shouldShowCommandInGeneralHelp(HelpableCommandInterface $command): bool
    {
        return in_array($command->getName(), $this->visibleCommands, true);
    }

    public function canViewCommandInHelp(HelpableCommandInterface $command): bool
    {
        return $this->shouldShowCommandInGeneralHelp($command) || in_array($command, $this->ircopCommands, true);
    }

    public function getHelpGroups(): array
    {
        if ([] !== $this->helpGroups) {
            return $this->helpGroups;
        }

        return [
            ['commands' => ['VISIBLE'], 'admin' => false],
            ['commands' => ['APROBAR', 'SAREGISTER'], 'admin' => true],
        ];
    }

    public function getIrcopCommands(): iterable
    {
        return $this->ircopCommands;
    }

    public function hasIrcopAccess(): bool
    {
        return $this->ircopAccess;
    }
}

final readonly class CRegServHelpableCommand implements HelpableCommandInterface
{
    /**
     * @param list<array{name: string, desc_key: string, help_key: string, syntax_key: string, options_key?: string}> $subcommands
     * @param array<string, mixed>                                                                                    $helpParams
     */
    public function __construct(
        private string $name,
        private int $order,
        private array $subcommands = [],
        private array $helpParams = [],
    ) {}

    public function getName(): string
    {
        return $this->name;
    }

    public function getOrder(): int
    {
        return $this->order;
    }

    public function getShortDescKey(): string
    {
        return strtolower($this->name) . '.short';
    }

    public function getSyntaxKey(): string
    {
        return strtolower($this->name) . '.syntax';
    }

    public function getHelpKey(): string
    {
        return strtolower($this->name) . '.help';
    }

    public function getSubCommandHelp(): array
    {
        return $this->subcommands;
    }

    public function isOperOnly(): bool
    {
        return false;
    }

    /** @return array<string, mixed> */
    public function getHelpParams(): array
    {
        return $this->helpParams;
    }
}

/** A command that opts into the HELP example line, mirroring Registra/Estado/CANCEL. */
final readonly class CRegServHelpableCommandWithExample implements HelpableCommandInterface
{
    public function __construct(
        private string $name,
        private int $order,
    ) {}

    public function getName(): string
    {
        return $this->name;
    }

    public function getOrder(): int
    {
        return $this->order;
    }

    public function getShortDescKey(): string
    {
        return strtolower($this->name) . '.short';
    }

    public function getSyntaxKey(): string
    {
        return strtolower($this->name) . '.syntax';
    }

    public function getHelpKey(): string
    {
        return strtolower($this->name) . '.help';
    }

    public function getSubCommandHelp(): array
    {
        return [];
    }

    public function isOperOnly(): bool
    {
        return false;
    }

    public function getExampleKey(): string
    {
        return strtolower($this->name) . '.example';
    }
}
