<?php

declare(strict_types=1);

namespace App\GlobalServ\Adapter\In\Irc\Command;

use App\GlobalServ\Adapter\In\Irc\GlobalServCommandInterface;
use App\GlobalServ\Adapter\In\Irc\GlobalServContext;
use App\GlobalServ\Application\Model\BroadcastOutcome;
use App\GlobalServ\Application\Model\BroadcastResult;
use App\GlobalServ\Application\Security\GlobalServPermission;
use App\GlobalServ\Application\UseCase\SendChannel\SendChannelMessage;
use App\GlobalServ\Application\UseCase\SendChannel\SendChannelMessageHandler;
use App\Irc\Application\Port\In\Command\CommandOutcome;

use function array_slice;
use function implode;

/** IRC parsing and presentation adapter for CHAN <#channel> <message>. */
final readonly class ChanCommand implements GlobalServCommandInterface
{
    public function __construct(private SendChannelMessageHandler $handler) {}

    public function getName(): string
    {
        return 'CHAN';
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
        return 'chan.syntax';
    }

    public function getHelpKey(): string
    {
        return 'chan.help';
    }

    public function getOrder(): int
    {
        return 3;
    }

    public function getShortDescKey(): string
    {
        return 'chan.short';
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
        return GlobalServPermission::CHAN;
    }

    public function execute(GlobalServContext $context): ?CommandOutcome
    {
        if (null === $context->sender) {
            return null;
        }

        $result = $this->handler->handle(new SendChannelMessage(
            actorNickname: $context->sender->nick,
            channelName: $context->args[0],
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
        if (BroadcastOutcome::Sent === $result->outcome) {
            $context->reply('chan.done', ['count' => (string) ($result->recipientCount ?? 0)]);

            return;
        }

        if (BroadcastOutcome::ChannelNotFound === $result->outcome) {
            $context->reply('chan.not_found', ['channel' => (string) $result->channelName]);

            return;
        }

        $context->reply('error.network_unavailable');
    }
}
