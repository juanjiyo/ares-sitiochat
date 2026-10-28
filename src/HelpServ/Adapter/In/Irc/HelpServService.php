<?php

declare(strict_types=1);

namespace App\HelpServ\Adapter\In\Irc;

use App\Irc\Application\Port\In\SenderView;
use App\Irc\Application\Port\In\ServiceNicknameRegistry;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Symfony\Contracts\Translation\TranslatorInterface;
use Throwable;

use function is_string;
use function preg_split;
use function strtoupper;
use function trim;

use const PREG_SPLIT_NO_EMPTY;

/**
 * Routes a raw command string (from a PRIVMSG to HeLP) to the correct command handler.
 */
final readonly class HelpServService
{
    public function __construct(
        private HelpServCommandRegistry $commandRegistry,
        private HelpServNotifierInterface $notifier,
        private TranslatorInterface $translator,
        private ServiceNicknameRegistry $serviceNicks,
        private string $defaultLanguage = 'en',
        private LoggerInterface $logger = new NullLogger(),
    ) {}

    /**
     * @param string     $rawText Full text of the PRIVMSG (e.g. "UMODES")
     * @param SenderView $sender  The user who sent the message (from NetworkUserLookupPort)
     */
    public function dispatch(string $rawText, SenderView $sender): void
    {
        $parts = preg_split('/\s+/', trim($rawText), -1, PREG_SPLIT_NO_EMPTY) ?: [];
        $cmdPart = array_shift($parts);
        $cmdName = strtoupper(is_string($cmdPart) ? $cmdPart : '');
        /** @var list<string> $args */
        $args = $parts;

        if ('' === $cmdName) {
            return;
        }

        $context = new HelpServContext(
            sender: $sender,
            command: $cmdName,
            args: $args,
            notifier: $this->notifier,
            translator: $this->translator,
            language: $this->defaultLanguage,
            registry: $this->commandRegistry,
            serviceNicks: $this->serviceNicks,
        );

        $handler = $this->commandRegistry->find($cmdName);

        if (null === $handler) {
            $context->reply('unknown_command', ['command' => $cmdName]);

            return;
        }

        try {
            $handler->execute($context);
        } catch (Throwable $e) {
            $this->logger->error('HelpServ dispatch error: ' . $e->getMessage(), [
                'exception' => $e,
                'sender' => $sender->uid,
            ]);
            throw $e;
        }
    }
}
