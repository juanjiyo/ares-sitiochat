<?php

declare(strict_types=1);

namespace App\HelpServ\Adapter\In\Irc;

use App\HelpServ\Adapter\In\Irc\Help\HelpableCommandInterface;
use App\Irc\Application\Port\In\Command\CommandOutcome;

/**
 * Contract every HeLPServ command module must implement.
 *
 * Commands are tagged with 'helpserv.command' and registered in HelpServCommandRegistry.
 */
interface HelpServCommandInterface extends HelpableCommandInterface
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

    /** Null = no permission required. HeLPServ has no permissions, so this is always null. */
    public function getRequiredPermission(): ?string;

    public function execute(HelpServContext $context): ?CommandOutcome;
}
