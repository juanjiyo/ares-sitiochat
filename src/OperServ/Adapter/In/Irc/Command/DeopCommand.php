<?php

declare(strict_types=1);

namespace App\OperServ\Adapter\In\Irc\Command;

use App\OperServ\Adapter\In\Irc\OperServCommandInterface;
use App\OperServ\Adapter\In\Irc\OperServContext;
use App\OperServ\Application\Security\OperServPermission;
use App\OperServ\Application\UseCase\ChannelOverride\SetChannelOperator;
use App\OperServ\Application\UseCase\ChannelOverride\SetChannelOperatorHandler;
use DateTimeImmutable;

/** IRC parsing adapter for DEOP <#channel> <nickname>. */
final readonly class DeopCommand implements OperServCommandInterface
{
    public function __construct(private SetChannelOperatorHandler $handler) {}

    public function getName(): string
    {
        return 'DEOP';
    }

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
        return 'deop.syntax';
    }

    public function getHelpKey(): string
    {
        return 'deop.help';
    }

    public function getExampleKey(): string
    {
        return 'deop.example';
    }

    public function getOrder(): int
    {
        return 13;
    }

    public function getShortDescKey(): string
    {
        return 'deop.short';
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
        return OperServPermission::OP;
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

        ChannelOverridePresenter::present($context, $this->handler->handle(new SetChannelOperator(
            actorNickname: $context->sender->nick,
            channelName: $context->args[0],
            targetNickname: $context->args[1],
            grant: false,
            occurredAt: new DateTimeImmutable(),
        )), 'deop.done');
    }
}
