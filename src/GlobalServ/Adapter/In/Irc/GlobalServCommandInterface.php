<?php

declare(strict_types=1);

namespace App\GlobalServ\Adapter\In\Irc;

use App\GlobalServ\Adapter\In\Irc\Help\HelpableCommandInterface;
use App\Irc\Application\Port\In\Command\CommandOutcome;

/**
 * Contract every GlobalServ command module must implement.
 *
 * Commands are tagged with 'globalserv.command' and registered in
 * GlobalServCommandRegistry.
 */
interface GlobalServCommandInterface extends HelpableCommandInterface
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

    /** Null = no permission; otherwise an IRCop permission such as globalserv.global. */
    public function getRequiredPermission(): ?string;

    public function execute(GlobalServContext $context): ?CommandOutcome;
}
