<?php

declare(strict_types=1);

namespace App\HelpServ\Adapter\In\Irc\Help;

/**
 * Interface for items that can be rendered by the unified HELP formatter.
 *
 * HeLPServ has no oper-only commands and no subcommand options, so this is a
 * smaller contract than the CRegServ equivalent.
 */
interface HelpableCommandInterface
{
    public function getName(): string;

    public function getOrder(): int;

    public function getShortDescKey(): string;

    public function getSyntaxKey(): string;

    public function getHelpKey(): string;

    /**
     * @return array<array{name: string, desc_key: string, help_key: string, syntax_key: string}>
     */
    public function getSubCommandHelp(): array;
}
