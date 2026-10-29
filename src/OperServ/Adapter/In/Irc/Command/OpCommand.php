<?php

declare(strict_types=1);

namespace App\OperServ\Adapter\In\Irc\Command;

use App\OperServ\Adapter\In\Irc\OperServCommandInterface;
use App\OperServ\Adapter\In\Irc\OperServContext;
use App\OperServ\Application\Security\OperServPermission;
use App\OperServ\Application\UseCase\ChannelOverride\SetChannelOperator;
use App\OperServ\Application\UseCase\ChannelOverride\SetChannelOperatorHandler;
use DateTimeImmutable;

/** IRC parsing adapter for OP <#channel> <nickname>. */
final readonly class OpCommand implements OperServCommandInterface
{
    public function __construct(private SetChannelOperatorHandler $handler) {}

    public function getName(): string
    {
        return 'OP';
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
        return 'op.syntax';
    }

    public function getHelpKey(): string
    {
        return 'op.help';
    }

    public function getExampleKey(): string
    {
        return 'op.example';
    }

    public function getOrder(): int
    {
        return 12;
    }

    public function getShortDescKey(): string
    {
        return 'op.short';
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
            grant: true,
            occurredAt: new DateTimeImmutable(),
        )), 'op.done');
    }
}
