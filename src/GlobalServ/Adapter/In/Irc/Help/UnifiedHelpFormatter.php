<?php

declare(strict_types=1);

namespace App\GlobalServ\Adapter\In\Irc\Help;

use function str_pad;
use function str_repeat;
use function usort;

/**
 * Renders unified HELP output (header, command list, syntax, footer) for GlobalServ.
 *
 * Adapted from the CRegServ formatter: GlobalServ has no command groups and no
 * subcommand options, so the contract is narrower — one general section plus an
 * IRCop section when the sender holds GlobalServ permissions.
 */
final readonly class UnifiedHelpFormatter
{
    private const int CMD_PAD = 12;

    private const int HEADER_WIDTH = 40;

    public function renderHeader(HelpFormatterContextInterface $context, string $title): string
    {
        $visible = 3 + mb_strlen($title);
        $dashes = str_repeat('─', max(0, self::HEADER_WIDTH - $visible));

        return $context->trans('help.header', [
            'title' => $title,
            'separator' => $dashes,
        ]);
    }

    public function renderFooter(HelpFormatterContextInterface $context): string
    {
        return $context->trans('help.footer');
    }

    /** @return list<string> */
    public function renderGeneralHelp(HelpFormatterContextInterface $context): array
    {
        /** @var list<HelpableCommandInterface> $items */
        $items = [];
        foreach ($context->getCommandsForGeneralHelp() as $item) {
            if ('HELP' !== $item->getName() && $context->shouldShowCommandInGeneralHelp($item)) {
                $items[] = $item;
            }
        }

        usort($items, static fn (HelpableCommandInterface $a, HelpableCommandInterface $b): int => $a->getOrder() <=> $b->getOrder());

        $lines = [$this->renderHeader($context, $context->trans('help.header_title'))];
        $lines[] = $context->trans('help.intro');
        $lines[] = ' ';
        $lines[] = $context->trans('help.general_header');

        foreach ($items as $item) {
            $lines[] = $context->trans('help.command_line', [
                'command' => str_pad($item->getName(), self::CMD_PAD),
                'description' => $context->trans($item->getShortDescKey()),
            ]);
        }

        if ($context->hasIrcopAccess()) {
            $lines[] = ' ';
            $lines[] = $context->trans('help.ircop_header');

            /** @var list<HelpableCommandInterface> $ircopCommands */
            $ircopCommands = [];
            foreach ($context->getIrcopCommands() as $command) {
                if ('HELP' !== $command->getName()) {
                    $ircopCommands[] = $command;
                }
            }

            usort($ircopCommands, static fn (HelpableCommandInterface $a, HelpableCommandInterface $b): int => $a->getOrder() <=> $b->getOrder());

            foreach ($ircopCommands as $command) {
                $lines[] = $context->trans('help.command_line', [
                    'command' => str_pad($command->getName(), self::CMD_PAD),
                    'description' => $context->trans($command->getShortDescKey()),
                ]);
            }
        }

        $lines[] = ' ';
        $lines[] = $context->trans('help.general_footer');

        return $lines;
    }

    /** @return list<string> */
    public function renderCommandHelp(HelpFormatterContextInterface $context, HelpableCommandInterface $item): array
    {
        $lines = [$this->renderHeader($context, 'HELP ' . $item->getName())];
        $lines[] = $context->trans($item->getHelpKey());
        $lines[] = ' ';
        $lines[] = $context->trans('help.syntax_label', ['syntax' => $context->trans($item->getSyntaxKey())]);
        $lines[] = $this->renderFooter($context);

        return $lines;
    }
}
