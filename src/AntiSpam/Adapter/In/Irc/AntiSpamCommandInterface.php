<?php

declare(strict_types=1);

namespace App\AntiSpam\Adapter\In\Irc;

use App\AntiSpam\Adapter\In\Irc\Help\HelpableCommandInterface;
use App\Irc\Application\Port\In\Command\CommandOutcome;

/**
 * Contract every AntiSpam command module must implement.
 *
 * Commands are tagged with 'antispam.command' and registered in
 * AntiSpamCommandRegistry.
 */
interface AntiSpamCommandInterface extends HelpableCommandInterface
{
    public function getName(): string;

    /** @return string[] */
    public function getAliases(): array;

    public function getMinArgs(): int;

    public function getSyntaxKey(): string;

    public function getHelpKey(): string;

    public function getOrder(): int;

    public function getShortDescKey(): string;

    /**
     * @return array<int, array{name: string, desc_key: string, help_key: string, syntax_key: string}>
     */
    public function getSubCommandHelp(): array;

    public function isOperOnly(): bool;

    /** Null = no permission; otherwise an IRCop permission such as antispam.global. */
    public function getRequiredPermission(): ?string;

    public function execute(AntiSpamContext $context): ?CommandOutcome;
}
