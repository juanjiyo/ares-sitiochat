<?php

declare(strict_types=1);

namespace App\GlobalServ\Adapter\In\Irc\Help;

/**
 * Port for the unified HELP formatter. Abstracts reply, translation and command
 * listing while the GlobalServ formatter owns the HELP layout.
 */
interface HelpFormatterContextInterface
{
    /**
     * @param array<string, mixed> $params
     */
    public function reply(string $key, array $params = []): void;

    public function replyRaw(string $message): void;

    /**
     * @param array<string, mixed> $params
     */
    public function trans(string $key, array $params = []): string;

    /**
     * Commands to list in general HELP.
     *
     * @return iterable<HelpableCommandInterface>
     */
    public function getCommandsForGeneralHelp(): iterable;

    public function shouldShowCommandInGeneralHelp(HelpableCommandInterface $command): bool;

    /**
     * Whether this sender may view detailed help for the command.
     */
    public function canViewCommandInHelp(HelpableCommandInterface $command): bool;

    /**
     * Returns IRCop commands that the current sender is authorized for. Used to
     * render the separated IRCop section in HELP.
     *
     * @return iterable<HelpableCommandInterface>
     */
    public function getIrcopCommands(): iterable;

    /**
     * Whether the sender holds at least one GlobalServ IRCop permission.
     */
    public function hasIrcopAccess(): bool;
}
