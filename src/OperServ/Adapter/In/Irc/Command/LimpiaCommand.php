<?php

declare(strict_types=1);

namespace App\OperServ\Adapter\In\Irc\Command;

use App\OperServ\Adapter\In\Irc\OperServCommandInterface;
use App\OperServ\Adapter\In\Irc\OperServContext;
use App\OperServ\Application\Security\OperServPermission;
use App\OperServ\Application\UseCase\ChannelOverride\SetChannelModes;
use App\OperServ\Application\UseCase\ChannelOverride\SetChannelModesHandler;
use DateTimeImmutable;

/** IRC parsing adapter for LIMPIA <#channel>. */
final readonly class LimpiaCommand implements OperServCommandInterface
{
    public function __construct(private SetChannelModesHandler $handler) {}

    public function getName(): string
    {
        return 'LIMPIA';
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
        return 'limpia.syntax';
    }

    public function getHelpKey(): string
    {
        return 'limpia.help';
    }

    public function getExampleKey(): string
    {
        return 'limpia.example';
    }

    public function getOrder(): int
    {
        return 15;
    }

    public function getShortDescKey(): string
    {
        return 'limpia.short';
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

        ChannelOverridePresenter::present($context, $this->handler->handle(SetChannelModes::cleanup(
            actorNickname: $context->sender->nick,
            channelName: $context->args[0],
            occurredAt: new DateTimeImmutable(),
        )), 'limpia.done', 'modes');
    }
}
