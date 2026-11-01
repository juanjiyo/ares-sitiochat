<?php

declare(strict_types=1);

namespace App\AntiSpam\Adapter\In\Irc\Command;

use App\AntiSpam\Adapter\In\Irc\AntiSpamAuthorizationCheckerInterface;
use App\AntiSpam\Adapter\In\Irc\AntiSpamCommandInterface;
use App\AntiSpam\Adapter\In\Irc\AntiSpamContext;
use App\AntiSpam\Adapter\In\Irc\Help\UnifiedHelpFormatter;
use App\AntiSpam\Adapter\In\Irc\HelpFormatterContextAdapter;
use App\Irc\Application\Port\In\Command\CommandOutcome;

use function strtoupper;

/**
 * Handles the HELP command: HELP [command].
 *
 * Lists the AntiSpam commands (the IRCop ones only for an oper holding their
 * permission) or shows help for a single command.
 */
final readonly class HelpCommand implements AntiSpamCommandInterface
{
    public function __construct(
        private UnifiedHelpFormatter $formatter,
        private AntiSpamAuthorizationCheckerInterface $authorizationChecker,
    ) {}

    public function getName(): string
    {
        return 'HELP';
    }

    /** @return list<string> */
    public function getAliases(): array
    {
        return ['AYUDA'];
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
        return 99;
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

    public function execute(AntiSpamContext $context): ?CommandOutcome
    {
        $adapter = new HelpFormatterContextAdapter($context, $this->authorizationChecker);

        if ([] === $context->args) {
            $lines = $this->formatter->renderGeneralHelp($adapter);
            $lines[] = $this->formatter->renderFooter($adapter);
            $context->replyRaw(implode("\n", $lines));

            return null;
        }

        $targetCmd = strtoupper($context->args[0]);
        $handler = $context->getRegistry()->find($targetCmd);

        if (null === $handler || !$adapter->canViewCommandInHelp($handler)) {
            $context->reply('help.unknown_command', ['command' => $targetCmd]);

            return null;
        }

        $context->replyRaw(implode("\n", $this->formatter->renderCommandHelp($adapter, $handler)));

        return null;
    }
}
