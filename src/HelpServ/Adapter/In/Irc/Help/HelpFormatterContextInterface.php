<?php

declare(strict_types=1);

namespace App\HelpServ\Adapter\In\Irc\Help;

/**
 * Port for the unified HELP formatter. Abstracts reply, translation and the
 * list of helpable items while the formatter owns the HELP layout.
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
     * Items to list in general HELP.
     *
     * @return iterable<HelpableCommandInterface>
     */
    public function getCommandsForGeneralHelp(): iterable;
}
