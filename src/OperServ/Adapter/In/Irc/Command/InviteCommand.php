<?php

declare(strict_types=1);

namespace App\OperServ\Adapter\In\Irc\Command;

use App\OperServ\Adapter\In\Irc\OperServCommandInterface;
use App\OperServ\Adapter\In\Irc\OperServContext;
use App\OperServ\Application\Security\OperServPermission;
use App\OperServ\Application\UseCase\ChannelOverride\InviteOperator;
use App\OperServ\Application\UseCase\ChannelOverride\InviteOperatorHandler;
use DateTimeImmutable;

/** IRC parsing adapter for INVITE <#channel>. */
final readonly class InviteCommand implements OperServCommandInterface
{
    public function __construct(private InviteOperatorHandler $handler) {}

    public function getName(): string
    {
        return 'INVITE';
    }

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
        return 'invite.syntax';
    }

    public function getHelpKey(): string
    {
        return 'invite.help';
    }

    public function getExampleKey(): string
    {
        return 'invite.example';
    }

    public function getOrder(): int
    {
        return 16;
    }

    public function getShortDescKey(): string
    {
        return 'invite.short';
    }

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
        return OperServPermission::INVITE;
    }

    /** @return array<string, string> */
    public function getHelpParams(): array
    {
        return [];
    }

    public function execute(OperServContext $context): void
    {
        if (null === $context->sender) {
            return;
        }

        ChannelOverridePresenter::present($context, $this->handler->handle(new InviteOperator(
            actorNickname: $context->sender->nick,
            actorUid: $context->sender->uid,
            channelName: $context->args[0],
            occurredAt: new DateTimeImmutable(),
        )), 'invite.done');
    }
}
