<?php

declare(strict_types=1);

namespace App\OperServ\Adapter\In\Irc\Command;

use App\OperServ\Adapter\In\Irc\OperServCommandInterface;
use App\OperServ\Adapter\In\Irc\OperServContext;
use App\OperServ\Application\Security\OperServPermission;
use App\OperServ\Application\UseCase\ChannelOverride\SetChannelModes;
use App\OperServ\Application\UseCase\ChannelOverride\SetChannelModesHandler;
use DateTimeImmutable;

use function array_slice;

/** IRC parsing adapter for MODE <#channel> <modes> [arguments]. */
final readonly class ModeCommand implements OperServCommandInterface
{
    public function __construct(private SetChannelModesHandler $handler) {}

    public function getName(): string
    {
        return 'MODE';
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
        return 'mode.syntax';
    }

    public function getHelpKey(): string
    {
        return 'mode.help';
    }

    public function getExampleKey(): string
    {
        return 'mode.example';
    }

    public function getOrder(): int
    {
        return 14;
    }

    public function getShortDescKey(): string
    {
        return 'mode.short';
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
        return OperServPermission::MODE;
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

        ChannelOverridePresenter::present($context, $this->handler->handle(SetChannelModes::custom(
            actorNickname: $context->sender->nick,
            channelName: $context->args[0],
            modes: $context->args[1],
            params: array_slice($context->args, 2),
            occurredAt: new DateTimeImmutable(),
        )), 'mode.done', 'modes');
    }
}
