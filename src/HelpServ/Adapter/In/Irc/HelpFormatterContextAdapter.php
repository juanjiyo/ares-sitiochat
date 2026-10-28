<?php

declare(strict_types=1);

namespace App\HelpServ\Adapter\In\Irc;

use App\HelpServ\Adapter\In\Irc\Help\HelpableCommandInterface;
use App\HelpServ\Adapter\In\Irc\Help\HelpFormatterContextInterface;
use App\HelpServ\Adapter\In\Irc\Help\Topic;

/**
 * Adapter from HelpServContext to HelpFormatterContextInterface for UnifiedHelpFormatter.
 */
final readonly class HelpFormatterContextAdapter implements HelpFormatterContextInterface
{
    public function __construct(
        private HelpServContext $context,
    ) {}

    /**
     * @param array<string, mixed> $params
     */
    public function reply(string $key, array $params = []): void
    {
        $this->context->reply($key, $params);
    }

    public function replyRaw(string $message): void
    {
        $this->context->replyRaw($message);
    }

    /**
     * @param array<string, mixed> $params
     */
    public function trans(string $key, array $params = []): string
    {
        return $this->context->trans($key, $params);
    }

    /**
     * @return iterable<HelpableCommandInterface>
     */
    public function getCommandsForGeneralHelp(): iterable
    {
        return Topic::all();
    }
}
