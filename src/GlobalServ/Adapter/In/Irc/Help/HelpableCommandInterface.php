<?php

declare(strict_types=1);

namespace App\GlobalServ\Adapter\In\Irc\Help;

/**
 * Interface for GlobalServ commands that can be rendered by the unified HELP
 * formatter. Contract copy of the CReG/HeLP house pattern; the service owns its
 * own instance under its own namespace.
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

    public function isOperOnly(): bool;
}
