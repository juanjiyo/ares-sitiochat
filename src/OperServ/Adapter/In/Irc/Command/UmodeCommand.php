<?php

declare(strict_types=1);

namespace App\OperServ\Adapter\In\Irc\Command;

use App\OperServ\Adapter\In\Irc\OperServCommandInterface;
use App\OperServ\Adapter\In\Irc\OperServContext;
use App\OperServ\Application\Security\OperServPermission;
use App\OperServ\Application\UseCase\UserMode\ChangeUserModes;
use App\OperServ\Application\UseCase\UserMode\ChangeUserModesHandler;
use App\OperServ\Application\UseCase\UserMode\UserModeOutcome;
use DateTimeImmutable;

/** IRC parsing adapter for UMODE <nickname> <+/-modes>. */
final readonly class UmodeCommand implements OperServCommandInterface
{
    public function __construct(private ChangeUserModesHandler $handler) {}

    public function getName(): string
    {
        return 'UMODE';
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
        return 'umode.syntax';
    }

    public function getHelpKey(): string
    {
        return 'umode.help';
    }

    public function getExampleKey(): string
    {
        return 'umode.example';
    }

    public function getOrder(): int
    {
        return 23;
    }

    public function getShortDescKey(): string
    {
        return 'umode.short';
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
        return OperServPermission::UMODE;
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

        $result = $this->handler->handle(new ChangeUserModes(
            actorNickname: $context->sender->nick,
            targetNickname: $context->args[0],
            modes: $context->args[1],
            occurredAt: new DateTimeImmutable(),
        ));
        $nickname = ['nickname' => $result->targetNickname];

        match ($result->outcome) {
            UserModeOutcome::Applied => $context->reply('umode.done', [...$nickname, 'modes' => $result->detail]),
            UserModeOutcome::InvalidModes => $context->reply('umode.invalid_modes', ['modes' => $result->detail]),
            UserModeOutcome::ForbiddenModes => $context->reply('umode.forbidden'),
            UserModeOutcome::AboveRank => $context->reply('umode.above_rank', ['modes' => $result->detail]),
            UserModeOutcome::NotOnline => $context->reply('channel.user_not_online', $nickname),
            UserModeOutcome::ProtectedRoot => $context->reply('channel.protected_root', $nickname),
            UserModeOutcome::ProtectedIrcOperator => $context->reply('channel.protected_ircop', $nickname),
            UserModeOutcome::NetworkUnavailable => null,
        };
    }
}
