<?php

declare(strict_types=1);

namespace App\GlobalServ\Adapter\In\Irc\Command;

use App\GlobalServ\Adapter\In\Irc\GlobalServCommandInterface;
use App\GlobalServ\Adapter\In\Irc\GlobalServContext;
use App\GlobalServ\Application\Security\GlobalServPermission;
use App\GlobalServ\Application\UseCase\Global\SendPublicGlobalMessage;
use App\GlobalServ\Application\UseCase\Global\SendPublicGlobalMessageHandlerInterface;
use App\GlobalServ\Application\UseCase\Global\SendPublicGlobalMessageOutcome;
use App\Irc\Application\Port\In\Command\CommandOutcome;
use DateTimeImmutable;

use function implode;

/**
 * IRC parsing and presentation adapter for GLOBAL <message>.
 *
 * Broadcasts the message as a NOTICE to every connected user from the GlobalServ
 * pseudo-client, in the two-line style of the reference implementation.
 */
final readonly class GlobalCommand implements GlobalServCommandInterface
{
    public function __construct(private SendPublicGlobalMessageHandlerInterface $handler) {}

    public function getName(): string
    {
        return 'GLOBAL';
    }

    /** @return list<string> */
    public function getAliases(): array
    {
        return [];
    }

    public function getMinArgs(): int
    {
        return 1;
    }

    public function getSyntaxKey(): string
    {
        return 'global.syntax';
    }

    public function getHelpKey(): string
    {
        return 'global.help';
    }

    public function getOrder(): int
    {
        return 1;
    }

    public function getShortDescKey(): string
    {
        return 'global.short';
    }

    /**
     * @return array<int, array{name: string, desc_key: string, help_key: string, syntax_key: string}>
     */
    public function getSubCommandHelp(): array
    {
        return [];
    }

    public function isOperOnly(): bool
    {
        return true;
    }

    public function getRequiredPermission(): string
    {
        return GlobalServPermission::GLOBAL;
    }

    public function execute(GlobalServContext $context): ?CommandOutcome
    {
        if (null === $context->sender) {
            return null;
        }

        $result = $this->handler->handle(new SendPublicGlobalMessage(
            actorNickname: $context->sender->nick,
            message: $this->broadcastMessage($context),
            occurredAt: new DateTimeImmutable(),
        ));

        if (SendPublicGlobalMessageOutcome::Sent === $result->outcome) {
            $context->reply('global.done', [
                'count' => (string) ($result->recipientCount ?? 0),
            ]);
        }

        return null;
    }

    /** Assembles the presented two-line broadcast text from the user message. */
    private function broadcastMessage(GlobalServContext $context): string
    {
        return $context->trans('global.prefix') . "\n" . implode(' ', $context->args);
    }
}
