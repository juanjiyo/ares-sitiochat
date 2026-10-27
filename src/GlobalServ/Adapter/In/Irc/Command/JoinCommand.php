<?php

declare(strict_types=1);

namespace App\GlobalServ\Adapter\In\Irc\Command;

use App\GlobalServ\Adapter\In\Irc\GlobalServCommandInterface;
use App\GlobalServ\Adapter\In\Irc\GlobalServContext;
use App\GlobalServ\Application\Model\BroadcastOutcome;
use App\GlobalServ\Application\Model\BroadcastResult;
use App\GlobalServ\Application\Security\GlobalServPermission;
use App\GlobalServ\Application\UseCase\MassJoin\MassJoinChannel;
use App\GlobalServ\Application\UseCase\MassJoin\MassJoinChannelHandler;
use App\Irc\Application\Port\In\Command\CommandOutcome;

use function array_slice;
use function implode;

/**
 * IRC parsing and presentation adapter for JOIN <#channel> <reason>.
 *
 * Force-joining every user needs an SVSJOIN-equivalent capability that the
 * protocol port does not expose yet, so the use case reports it as unsupported.
 */
final readonly class JoinCommand implements GlobalServCommandInterface
{
    public function __construct(private MassJoinChannelHandler $handler) {}

    public function getName(): string
    {
        return 'JOIN';
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
        return 'join.syntax';
    }

    public function getHelpKey(): string
    {
        return 'join.help';
    }

    public function getOrder(): int
    {
        return 4;
    }

    public function getShortDescKey(): string
    {
        return 'join.short';
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
        return GlobalServPermission::JOIN;
    }

    public function execute(GlobalServContext $context): ?CommandOutcome
    {
        if (null === $context->sender) {
            return null;
        }

        $result = $this->handler->handle(new MassJoinChannel(
            actorNickname: $context->sender->nick,
            channelName: $context->args[0],
            reason: $context->trans('global.prefix') . "\n" . implode(' ', array_slice($context->args, 1)),
        ));

        $this->present($context, $result);

        return null;
    }

    private function present(GlobalServContext $context, BroadcastResult $result): void
    {
        match ($result->outcome) {
            BroadcastOutcome::Sent => $context->reply('join.done', ['count' => (string) ($result->recipientCount ?? 0)]),
            BroadcastOutcome::JoinUnsupported => $context->reply('join.unsupported'),
            BroadcastOutcome::NetworkUnavailable => $context->reply('error.network_unavailable'),
            default => $context->reply('error.broadcast_failed'),
        };
    }
}
