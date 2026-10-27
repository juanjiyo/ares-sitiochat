<?php

declare(strict_types=1);

namespace App\GlobalServ\Adapter\In\Irc\Command;

use App\GlobalServ\Adapter\In\Irc\GlobalServCommandInterface;
use App\GlobalServ\Adapter\In\Irc\GlobalServContext;
use App\GlobalServ\Application\Model\BroadcastOutcome;
use App\GlobalServ\Application\Model\BroadcastResult;
use App\GlobalServ\Application\Security\GlobalServPermission;
use App\GlobalServ\Application\UseCase\SendNick\SendNickBroadcast;
use App\GlobalServ\Application\UseCase\SendNick\SendNickBroadcastHandler;
use App\Irc\Application\Port\In\Command\CommandOutcome;

use function array_slice;
use function implode;

/** IRC parsing and presentation adapter for NICK <nick> <message>. */
final readonly class NickCommand implements GlobalServCommandInterface
{
    public function __construct(private SendNickBroadcastHandler $handler) {}

    public function getName(): string
    {
        return 'NICK';
    }

    /** @return list<string> */
    public function getAliases(): array
    {
        return [];
    }

    public function getMinArgs(): int
    {
        return 2;
    }

    public function getSyntaxKey(): string
    {
        return 'nick.syntax';
    }

    public function getHelpKey(): string
    {
        return 'nick.help';
    }

    public function getOrder(): int
    {
        return 2;
    }

    public function getShortDescKey(): string
    {
        return 'nick.short';
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
        return GlobalServPermission::NICK;
    }

    public function execute(GlobalServContext $context): ?CommandOutcome
    {
        if (null === $context->sender) {
            return null;
        }

        $result = $this->handler->handle(new SendNickBroadcast(
            actorNickname: $context->sender->nick,
            nick: $context->args[0],
            message: $this->broadcastMessage($context),
        ));

        $this->present($context, $result);

        return null;
    }

    private function broadcastMessage(GlobalServContext $context): string
    {
        return $context->trans('global.prefix') . "\n" . implode(' ', array_slice($context->args, 1));
    }

    private function present(GlobalServContext $context, BroadcastResult $result): void
    {
        match ($result->outcome) {
            BroadcastOutcome::Sent => $context->reply('nick.done', ['count' => (string) ($result->recipientCount ?? 0)]),
            BroadcastOutcome::NetworkUnavailable => $context->reply('error.network_unavailable'),
            default => $context->reply('error.broadcast_failed'),
        };
    }
}
