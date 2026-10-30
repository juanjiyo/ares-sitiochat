<?php

declare(strict_types=1);

namespace App\CRegServ\Adapter\In\Irc\Help;

use function is_array;
use function is_string;
use function strtoupper;

/**
 * Renders unified HELP output (header, command list, options, syntax, footer)
 * within CReG. The context supplies translations and command visibility.
 *
 * Copy of ChanServ's formatter adapted to CReG and styled after the plain
 * ChatHispano layout: no emoji, no box drawing, section headers plus aligned
 * command rows. Each service owns its own formatter instance under its own
 * namespace (house pattern — see OperServ, MemoServ and NickServ), and deptrac
 * forbids CReG from reaching into ChanServ.
 */
final readonly class UnifiedHelpFormatter
{
    private const int CMD_PAD = 13;

    private const int SUBS_PAD = 10;

    public function __construct(
        private string $networkName,
    ) {}

    /** @return list<string> */
    public function renderGeneralHelp(HelpFormatterContextInterface $context): array
    {
        /** @var array<string, HelpableCommandInterface> $visibleCommands */
        $visibleCommands = [];
        foreach ($context->getCommandsForGeneralHelp() as $command) {
            if ('HELP' !== $command->getName() && $context->shouldShowCommandInGeneralHelp($command)) {
                $visibleCommands[strtoupper($command->getName())] = $command;
            }
        }
        if ($context->hasIrcopAccess()) {
            foreach ($context->getIrcopCommands() as $command) {
                if ('HELP' !== $command->getName()) {
                    $visibleCommands[strtoupper($command->getName())] = $command;
                }
            }
        }

        $lines = [$context->trans('help.header_general', ['network' => $this->networkName])];
        $lines[] = $context->trans('help.intro');
        $lines[] = ' ';
        $lines[] = $context->trans('help.general_header');

        $shown = [];
        $ircopHeaderSent = false;
        foreach ($context->getHelpGroups() as $group) {
            $groupCommands = [];
            foreach ($group['commands'] as $name) {
                $name = strtoupper($name);
                if (isset($visibleCommands[$name]) && !isset($shown[$name])) {
                    $groupCommands[] = $visibleCommands[$name];
                    $shown[$name] = true;
                }
            }
            if ([] === $groupCommands) {
                continue;
            }
            usort($groupCommands, static fn (HelpableCommandInterface $a, HelpableCommandInterface $b): int => $a->getOrder() <=> $b->getOrder());

            if ($group['admin'] && !$ircopHeaderSent) {
                $lines[] = ' ';
                $lines[] = $context->trans('help.ircop_header');
                $ircopHeaderSent = true;
            }

            foreach ($groupCommands as $command) {
                $lines[] = $context->trans('help.command_line', [
                    'command' => str_pad($command->getName(), self::CMD_PAD),
                    'description' => $context->trans($command->getShortDescKey()),
                ]);
            }
        }

        // Keep a visible fallback for any future command not yet assigned to a group.
        $ungroupedCommands = [];
        foreach ($visibleCommands as $name => $command) {
            if (!isset($shown[$name])) {
                $ungroupedCommands[] = $command;
            }
        }
        usort($ungroupedCommands, static fn (HelpableCommandInterface $a, HelpableCommandInterface $b): int => $a->getOrder() <=> $b->getOrder());
        foreach ($ungroupedCommands as $command) {
            $lines[] = $context->trans('help.command_line', [
                'command' => str_pad($command->getName(), self::CMD_PAD),
                'description' => $context->trans($command->getShortDescKey()),
            ]);
        }

        $lines[] = ' ';
        $lines[] = $context->trans('help.warning');
        $lines[] = ' ';
        $lines[] = $context->trans('help.footer_general');

        return $lines;
    }

    /** @return list<string> */
    public function renderCommandHelp(HelpFormatterContextInterface $context, HelpableCommandInterface $handler): array
    {
        $lines = [$context->trans('help.command_header', ['command' => $handler->getName()])];

        $rawParams = method_exists($handler, 'getHelpParams') ? $handler->getHelpParams() : [];
        /** @var array<string, mixed> $params */
        $params = is_array($rawParams) ? $rawParams : [];
        $lines[] = $context->trans($handler->getHelpKey(), $params);

        $subCmds = $handler->getSubCommandHelp();

        if ([] !== $subCmds) {
            $lines[] = ' ';
            $lines[] = $context->trans('help.options_header');

            foreach ($subCmds as $sub) {
                $lines[] = $context->trans('help.subcommand_line', [
                    'command' => str_pad($sub['name'], self::SUBS_PAD),
                    'description' => $context->trans($sub['desc_key']),
                ]);
            }

            $lines[] = ' ';
            $lines[] = $context->trans('help.set_sub_footer', [
                'command' => $handler->getName(),
            ]);
        }

        $lines[] = ' ';
        $lines[] = $context->trans('help.syntax_label', ['syntax' => $context->trans($handler->getSyntaxKey())]);

        $exampleKey = method_exists($handler, 'getExampleKey') ? $handler->getExampleKey() : null;
        if (is_string($exampleKey)) {
            $lines[] = ' ';
            $lines[] = $context->trans('help.example_label', ['example' => $context->trans($exampleKey)]);
        }

        $lines[] = ' ';
        $lines[] = $context->trans('help.footer_command', ['command' => $handler->getName()]);

        return $lines;
    }

    /**
     * @param array{name: string, help_key: string, syntax_key: string, options_key?: string} $sub
     *
     * @return list<string>
     */
    public function renderSubCommandHelp(HelpFormatterContextInterface $context, string $parentName, array $sub): array
    {
        $lines = [$context->trans('help.command_header', ['command' => $parentName . ' ' . $sub['name']])];
        $lines[] = $context->trans($sub['help_key']);
        if (isset($sub['options_key'])) {
            $lines[] = ' ';
            $lines[] = $context->trans($sub['options_key']);
        }
        $lines[] = ' ';
        $lines[] = $context->trans('help.syntax_label', ['syntax' => $context->trans($sub['syntax_key'])]);
        $lines[] = ' ';
        $lines[] = $context->trans('help.footer_command', ['command' => $parentName . ' ' . $sub['name']]);

        return $lines;
    }
}
