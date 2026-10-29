<?php

declare(strict_types=1);

namespace App\OperServ\Adapter\In\Irc\Command;

use App\OperServ\Adapter\In\Irc\OperServCommandInterface;
use App\OperServ\Adapter\In\Irc\OperServContext;
use App\OperServ\Application\Security\OperServPermission;
use App\OperServ\Application\UseCase\ChannelOverride\KickChannelMember;
use App\OperServ\Application\UseCase\ChannelOverride\KickChannelMemberHandler;
use DateTimeImmutable;

use function array_slice;
use function implode;

/** IRC parsing adapter for KICK <#channel> <nickname> [reason]. */
final readonly class KickCommand implements OperServCommandInterface
{
    public function __construct(private KickChannelMemberHandler $handler) {}

    public function getName(): string
    {
        return 'KICK';
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
        return 'kick.syntax';
    }

    public function getHelpKey(): string
    {
        return 'kick.help';
    }

    public function getExampleKey(): string
    {
        return 'kick.example';
    }

    public function getOrder(): int
    {
        return 11;
    }

    public function getShortDescKey(): string
    {
        return 'kick.short';
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
        return OperServPermission::KICK;
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

        ChannelOverridePresenter::present($context, $this->handler->handle(new KickChannelMember(
            actorNickname: $context->sender->nick,
            serviceNickname: $context->getBotName(),
            channelName: $context->args[0],
            targetNickname: $context->args[1],
            reason: implode(' ', array_slice($context->args, 2)),
            occurredAt: new DateTimeImmutable(),
        )), 'kick.done', 'reason');
    }
}
