<?php

declare(strict_types=1);

namespace App\HelpServ\Adapter\In\Irc\Command;

use App\HelpServ\Adapter\In\Irc\Help\Topic;
use App\HelpServ\Adapter\In\Irc\Help\UnifiedHelpFormatter;
use App\HelpServ\Adapter\In\Irc\HelpFormatterContextAdapter;
use App\HelpServ\Adapter\In\Irc\HelpServCommandInterface;
use App\HelpServ\Adapter\In\Irc\HelpServContext;
use App\Irc\Application\Port\In\Command\CommandOutcome;

use function implode;
use function in_array;
use function strtoupper;

/**
 * Handles the HELP command and every help topic.
 *
 * HeLPServ exposes prose topics (UMODES, CMODES, BOTS, NICK, CANAL, CREDITOS)
 * both as HELP arguments and as direct commands, matching the original dbots
 * behaviour where /msg HeLP UMODES and /msg HeLP HELP UMODES are equivalent.
 */
final readonly class HelpCommand implements HelpServCommandInterface
{
    private const array TOPIC_COMMANDS = ['UMODES', 'CMODES', 'BOTS', 'NICK', 'CANAL', 'CREDITOS'];

    public function __construct(
        private UnifiedHelpFormatter $formatter,
    ) {}

    public function getName(): string
    {
        return 'HELP';
    }

    /** @return list<string> */
    public function getAliases(): array
    {
        return ['AYUDA', ...self::TOPIC_COMMANDS];
    }

    public function getMinArgs(): int
    {
        return 0;
    }

    public function getSyntaxKey(): string
    {
        return 'help.syntax';
    }

    public function getHelpKey(): string
    {
        return 'help.help';
    }

    public function getOrder(): int
    {
        return 0;
    }

    public function getShortDescKey(): string
    {
        return 'help.short';
    }

    /**
     * @return array<int, array{name: string, desc_key: string, help_key: string, syntax_key: string}>
     */
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

    public function execute(HelpServContext $context): ?CommandOutcome
    {
        $adapter = new HelpFormatterContextAdapter($context);
        $topicName = $this->resolveTopicName($context);

        if (null === $topicName) {
            $lines = $this->formatter->renderGeneralHelp($adapter);
            $lines[] = $this->formatter->renderFooter($adapter);
            $context->replyRaw(implode("\n", $lines));

            return null;
        }

        $topic = Topic::fromName($topicName);

        if (null === $topic) {
            $context->reply('help.unknown_topic', ['topic' => $topicName]);

            return null;
        }

        $context->replyRaw(implode("\n", $this->formatter->renderCommandHelp($adapter, $topic)));

        return null;
    }

    private function resolveTopicName(HelpServContext $context): ?string
    {
        if (in_array($context->command, ['HELP', 'AYUDA'], true)) {
            return $context->args[0] ?? null;
        }

        return strtoupper($context->command);
    }
}
